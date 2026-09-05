<?php

namespace App\Services\Bot;

use App\Enums\UserResumeEnum;
use App\Helpers\ResponseData;
use App\Models\UserResume;
use App\Services\Bot\Actions\ProcessBotAction;
use App\Services\Bot\Actions\PublishBotAction;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessBotService
{
    private string $baseUrl;

    private float $connectTimeout;

    private float $healthTimeout;

    private float $requestTimeout;

    public function __construct(
        private $http = null,
        public string $endpointBase = 'api/v1'
    ) {
        $config = config('services.bot');

        $this->baseUrl = rtrim($config['url'] ?? '', '/');
        $this->connectTimeout = (float) ($config['connect_timeout_seconds'] ?? 5);
        $this->healthTimeout = (float) ($config['health_timeout_seconds'] ?? 5);
        $this->requestTimeout = (float) ($config['timeout_seconds'] ?? 260);
    }

    protected function checkHealth($http): void
    {
        $response = $http->get('/health');

        if (! $response->successful()) {
            throw new RequestException($response);
        }

        if ($response->json('status') !== 'online') {
            throw new Exception('BOT OFFLINE', 503);
        }
    }

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

        try {
            if (empty($this->baseUrl)) {
                throw new Exception('BOT_URL is not configured.');
            }

            $this->checkHealth(
                Http::acceptJson()
                    ->connectTimeout($this->connectTimeout)
                    ->timeout($this->healthTimeout)
                    ->baseUrl($this->baseUrl)
            );

            $endpointBase = trim($this->endpointBase, '/');
            $this->http = Http::acceptJson()
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->requestTimeout)
                ->baseUrl($this->baseUrl.'/'.$endpointBase);

            $callback = (new PublishBotAction(
                $this->http,
                $request->user(),
                $resume
            ))::handle();

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
                'bot_url' => $this->baseUrl,
                'exception' => $exception,
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
