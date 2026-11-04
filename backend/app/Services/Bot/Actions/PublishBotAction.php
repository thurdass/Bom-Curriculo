<?php

namespace App\Services\Bot\Actions;

use App\Models\User;
use App\Models\UserResume;
use App\Services\Resume\JsonObject;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

class PublishBotAction
{
    public function __construct(
        private $http,
        private User $user,
        private UserResume $resume,
        private string $endpoint = '/build',
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     * @throws JsonException
     */
    public function handle(): array
    {
        $bot = $this->build($this->resume, $this->user);

        if (! $bot->successful()) {
            throw new RequestException($bot);
        }

        $data = $bot->json();

        try {
            return JsonObject::decode(is_string($data) ? $data : $bot->body());
        } catch (RuntimeException $error) {
            throw new RuntimeException('Bot returned an invalid response.', 502, $error);
        }
    }

    /**
     * @throws JsonException
     */
    private function build(UserResume $resume, User $user): Response
    {
        $cvPath = $resume->original_file_path_cv;

        if (empty($cvPath) || ! Storage::exists($cvPath)) {
            throw new RuntimeException('Resume CV file is missing.', 422);
        }

        $cvStream = Storage::readStream($cvPath);

        if (! is_resource($cvStream)) {
            throw new RuntimeException('Resume CV file could not be opened.', 500);
        }

        $linkedinStream = null;
        $linkedinPath = $resume->original_file_path_linkedin;

        try {
            $request = $this->http->attach(
                'resume_cv',
                $cvStream,
                basename($cvPath)
            );

            if (! empty($linkedinPath) && Storage::exists($linkedinPath)) {
                $linkedinStream = Storage::readStream($linkedinPath);

                if (! is_resource($linkedinStream)) {
                    throw new RuntimeException('LinkedIn resume file could not be opened.', 500);
                }

                $request = $request->attach(
                    'resume_linkedin',
                    $linkedinStream,
                    basename($linkedinPath)
                );
            }

            // Old uploads have no snapshot; keep their original profile-based behavior.
            $sources = $resume->source_context ?? [
                'additional_skills' => $user->skills()->get(['name', 'years'])->toArray(),
                'github_url' => $user->github_link,
                'portfolio_url' => $user->site_link,
            ];
            $form = [
                'additional_skills' => json_encode(
                    $sources['additional_skills'],
                    JSON_THROW_ON_ERROR
                ),
            ];

            if (! empty($sources['github_url'])) {
                $form['github_url'] = $sources['github_url'];
            }

            if (! empty($sources['portfolio_url'])) {
                $form['portfolio_url'] = $sources['portfolio_url'];
            }

            return $request->post($this->endpoint, $form);
        } finally {
            fclose($cvStream);

            if (is_resource($linkedinStream)) {
                fclose($linkedinStream);
            }
        }
    }
}
