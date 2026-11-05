<?php

namespace App\Http\Controllers\Api\Resume;

use App\Enums\UserResumeEnum;
use App\Helpers\ResponseData;
use App\Http\Controllers\Controller;
use App\Services\Resume\ResumeAnalysisPersister;
use App\Services\Resume\ResumeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class ResumeProcessingController extends Controller
{
    public function __invoke(Request $request, ResumeGenerator $generator, ResumeAnalysisPersister $persister)
    {
        $id = $request->input('user_resume_id');
        if (! is_string($id) || ! Str::isUuid($id)) {
            return ResponseData::error('Validation error', ['message' => 'A valid user_resume_id UUID is required.'], 422);
        }
        $resume = $request->user()->resumes()->find($id);
        if (! $resume) {
            return ResponseData::error('Not found!', ['message' => 'Resume not found for this user.'], 404);
        }
        $ttl = config('resume.ollama.timeout_seconds') + 2 * config('resume.documents.timeout_seconds') + 2 * config('resume.github.timeout_seconds') + 60;
        $lock = Cache::lock('resume-generation:'.$resume->id, $ttl);
        if (! $lock->get()) {
            return ResponseData::error('Processing', ['message' => 'This resume is already being processed.'], 409);
        }
        try {
            $payload = $generator->generate($resume, $request->user());

            return ResponseData::success('Processed successfuly', [$persister->persist($payload, $resume)]);
        } catch (Throwable $error) {
            $status = in_array($error->getCode(), [422, 502, 503], true) ? $error->getCode() : 500;
            $message = $status === 500 ? 'Could not process the resume.' : $error->getMessage();
            Log::warning('Resume processing failed.', ['user_resume_id' => $resume->id, 'exception_type' => get_class($error)]);
            $resume->update(['status' => UserResumeEnum::FAIL, 'observation' => $message]);

            return ResponseData::error('Failed', ['message' => $message], $status);
        } finally {
            $lock->release();
        }
    }
}
