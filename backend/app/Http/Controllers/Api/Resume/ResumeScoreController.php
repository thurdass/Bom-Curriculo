<?php

namespace App\Http\Controllers\Api\Resume;

use App\Helpers\ResponseData;
use App\Http\Controllers\Controller;
use App\Services\Resume\ResumeScorer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ResumeScoreController extends Controller
{
    public function __invoke(Request $request, string $resume, ResumeScorer $scorer)
    {
        $record = $request->user()->resumes()->findOrFail($resume);
        $ttl = config('resume.ollama.timeout_seconds') + 2 * config('resume.documents.timeout_seconds') + 2 * config('resume.github.timeout_seconds') + 60;
        $lock = Cache::lock('resume-generation:'.$record->id, $ttl);
        if (! $lock->get()) {
            return ResponseData::error('Processing', ['message' => 'This resume is already being processed.'], 409);
        }
        try {
            return ResponseData::success('Resume evaluated successfully.', $scorer->score($record, $request->user()));
        } catch (Throwable $error) {
            $status = in_array($error->getCode(), [422, 502, 503], true) ? $error->getCode() : 500;
            Log::warning('Resume scoring failed.', ['user_resume_id' => $record->id, 'exception_type' => get_class($error)]);

            return ResponseData::error('Evaluation failed.', ['message' => $status === 500 ? 'Could not evaluate the resume.' : $error->getMessage()], $status);
        } finally {
            $lock->release();
        }
    }
}
