<?php

namespace App\Services\Resume;

use App\Models\User;
use App\Models\UserResume;
use App\Services\Bot\Actions\PublishBotAction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/** Temporary Python document/GitHub adapter, replaceable independently of inference. */
final class ResumeSourcePreparer
{
    public function prepare(UserResume $resume, User $user): array
    {
        $url = config('services.bot.url');
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Document preparation is not configured.', 503);
        }
        try {
            $sources = (new PublishBotAction(
                Http::acceptJson()->baseUrl(rtrim($url, '/').'/api/v1')
                    ->connectTimeout(config('services.bot.connect_timeout_seconds'))
                    ->timeout(config('resume.prepare_timeout_seconds')),
                $user, $resume, '/prepare',
            ))->handle();
        } catch (ConnectionException $error) {
            throw new RuntimeException('Document preparation is unavailable or timed out.', 503, $error);
        } catch (RequestException $error) {
            // Upstream errors may contain document text; never expose their body.
            throw new RuntimeException(
                $error->response->status() === 422 ? 'The uploaded documents could not be read or validated.' : 'Document preparation failed.',
                $error->response->status() === 422 ? 422 : 503, $error,
            );
        }

        if (($sources['version'] ?? null) !== 1 || Validator::make($sources, [
            'resume_text' => ['required', 'string'],
            'linkedin_text' => ['present', 'nullable', 'string'],
            'github_profile' => ['present', 'nullable', 'array'],
            'github_url' => ['present', 'nullable', 'string'],
            'portfolio_url' => ['present', 'nullable', 'string'],
            'additional_skills' => ['present', 'array'],
            'additional_skills.*.name' => ['required', 'string'],
            'additional_skills.*.years' => ['present', 'nullable', 'integer'],
        ])->fails()) {
            throw new RuntimeException('Invalid document preparation response.', 502);
        }

        return $sources;
    }
}
