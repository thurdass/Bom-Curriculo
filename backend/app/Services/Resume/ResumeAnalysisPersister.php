<?php

namespace App\Services\Resume;

use App\Enums\UserResumeEnum;
use App\Models\ResumeAnalytic;
use App\Models\UserResume;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ResumeAnalysisPersister
{
    public function persist(array $payload, UserResume $resume): ResumeAnalytic
    {
        $payload = array_key_exists('header', $payload)
            ? ResumePayload::validate($payload) : LegacyResumePayload::fromData($payload)->toArray();

        return DB::transaction(function () use ($payload, $resume): ResumeAnalytic {
            $analytic = ResumeAnalytic::query()->updateOrCreate(
                ['user_resume_id' => $resume->id],
                array_merge(ResumeProjection::fromPayload($payload), [
                    'analysis_request_id' => (string) Str::uuid(), 'user_id' => $resume->user_id,
                    'status' => 'success', 'error' => '', 'ai_payload' => $payload,
                ]),
            );
            $resume->update(['status' => UserResumeEnum::ANALYZE, 'observation' => null]);

            return $analytic;
        });
    }
}
