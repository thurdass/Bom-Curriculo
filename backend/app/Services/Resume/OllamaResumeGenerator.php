<?php

namespace App\Services\Resume;

use App\Models\User;
use App\Models\UserResume;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class OllamaResumeGenerator implements ResumeGenerator
{
    public function __construct(
        private ResumeSourcePreparer $preparer,
        private ResumePrompt $prompt,
        private OllamaClient $client,
    ) {}

    public function generate(UserResume $resume, User $user): array
    {
        $started = hrtime(true);
        $sources = $this->preparer->prepare($resume, $user);
        $prepared = hrtime(true);
        if (mb_strlen(json_encode($sources, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) > config('resume.ollama.max_source_characters')) {
            throw new RuntimeException('The documents exceed the configured generation input limit.', 422);
        }
        $payload = $this->client->generate($this->prompt->messages($sources), $resume->id);

        // User-provided facts take precedence over model guesses.
        $links = (array) $payload['header']['links'];
        $reportedLinks = [];
        foreach (['github_url' => 'GitHub', 'portfolio_url' => 'Portfolio'] as $field => $label) {
            if (! empty($sources[$field])) {
                $reportedLinks[$label] = $sources[$field];
            }
        }
        $payload['header']['links'] = (object) ResumeLinks::merge($links, $reportedLinks);
        foreach ($sources['additional_skills'] as $reported) {
            $found = false;
            foreach ($payload['skills'] as &$skill) {
                if (mb_strtolower(trim($skill['name'])) === mb_strtolower(trim($reported['name']))) {
                    if ($reported['years'] !== null) {
                        $skill['years'] = (int) $reported['years'];
                    }
                    $found = true;
                    break;
                }
            }
            unset($skill);
            if (! $found) {
                $payload['skills'][] = ['name' => $reported['name'], 'years' => $reported['years'] === null ? null : (int) $reported['years']];
            }
        }

        $payload = ResumePayload::validate($payload);
        Log::info('Resume sources and generation completed.', [
            'user_resume_id' => $resume->id, 'provider' => 'ollama',
            'prepare_duration_ms' => round(($prepared - $started) / 1e6),
            'total_duration_ms' => round((hrtime(true) - $started) / 1e6),
        ]);

        return $payload;
    }
}
