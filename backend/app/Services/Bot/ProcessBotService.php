<?php

namespace App\Services\Bot;

use App\Enums\UserResumeEnum;
use App\Helpers\ResponseData;
use App\Models\UserResume;
use App\Services\Bot\Actions\ProcessBotAction;
use App\Services\Resume\ResumeGenerationService;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessBotService
{
    public function __construct(private ResumeGenerationService $generator) {}

    public function analyse(Request $request)
    {
        $resumeId = $request->input('user_resume_id');

        if (! is_string($resumeId) || ! Str::isUuid($resumeId)) {
            return ResponseData::error('Validation error', [
                'message' => 'A valid user_resume_id UUID is required.',
            ], 422);
        }

        $resume = UserResume::query()
            ->whereKey($resumeId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $resume) {
            return ResponseData::error('Not found!', [
                'message' => 'Resume not found for this user.',
            ], 404);
        }

        $ttl = (int) max(
            config('resume.prepare_timeout_seconds') + config('resume.ollama.timeout_seconds'),
            config('services.bot.health_timeout_seconds') + config('services.bot.timeout_seconds'),
        ) + 60;
        $lock = Cache::lock('resume-generation:'.$resume->id, $ttl);
        if (! $lock->get()) {
            return ResponseData::error('Processing', ['message' => 'This resume is already being processed.'], 409);
        }

        try {
            $callback = $this->generator->generate($resume, $request->user());

            $response = ProcessBotAction::handle($callback, $resume);

            if ($response === null) {
                throw new Exception('Error to generate callback!');
            }

            return ResponseData::success('Processed successfuly', [
                $response,
            ], 200);

        } catch (RequestException $exception) {
            $statusCode = $exception->response->status();
            $errorData = $exception->response->json();
            $details = is_array($errorData) ? $errorData : [];
            $message = $this->errorMessage(
                $details['detail'] ?? $details['message'] ?? $details['error'] ?? null,
                $exception->getMessage()
            );

            $this->markFailed($resume, $message);

            return ResponseData::error('Failed', [
                'message' => $message,
                'details' => $details,
            ], $this->errorStatus($statusCode));

        } catch (ConnectionException $exception) {
            Log::error('Bot service connection failed.', [
                'user_resume_id' => $resume->id,
                'exception_type' => get_class($exception),
            ]);

            $message = 'Bot service is unavailable.';

            $this->markFailed($resume, $message);

            return ResponseData::error('Failed', [
                'message' => $message,
            ], 503);

        } catch (Throwable $exception) {
            $code = $exception->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            $message = $this->errorMessage(null, $exception->getMessage());

            $this->markFailed($resume, $message);

            return ResponseData::error('Failed', [
                'message' => $message,
            ], $statusCode);
        } finally {
            $lock->release();
        }
    }

    private function markFailed(UserResume $resume, string $message): void
    {
        try {
            $resume->update([
                'status' => UserResumeEnum::FAIL,
                'observation' => $message,
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to update resume processing status.', [
                'user_resume_id' => $resume->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function errorMessage(mixed $detail, string $fallback): string
    {
        if (is_string($detail) && $detail !== '') {
            return $detail;
        }

        if ($detail !== null) {
            $encoded = json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (is_string($encoded)) {
                return $encoded;
            }
        }

        return $fallback !== '' ? $fallback : 'Bot processing failed.';
    }

    private function errorStatus(int $status): int
    {
        return $status >= 400 && $status < 600 ? $status : 502;
    }
}
