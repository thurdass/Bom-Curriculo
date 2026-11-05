<?php

namespace App\Services\Resume;

use App\Models\User;
use App\Models\UserResume;
use App\Services\Resume\Documents\DocumentTextExtractor;
use App\Services\Resume\Documents\ResumeContentValidator;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class ResumeSourcePreparer
{
    public function __construct(
        private DocumentTextExtractor $documents,
        private ResumeContentValidator $validator,
        private GitHubProfileFetcher $github,
    ) {}

    public function prepare(UserResume $resume, User $user): array
    {
        $context = $resume->source_context ?? [
            'github_url' => $user->github_link,
            'portfolio_url' => $user->site_link,
            'additional_skills' => $user->skills()->get(['name', 'years'])->toArray(),
        ];
        if (Validator::make($context, [
            'github_url' => ['present', 'nullable', 'string'],
            'portfolio_url' => ['present', 'nullable', 'string'],
            'additional_skills' => ['present', 'array'],
            'additional_skills.*.name' => ['required', 'string'],
            'additional_skills.*.years' => ['nullable', 'integer'],
        ])->fails()) {
            throw new RuntimeException('Invalid resume source information.', 422);
        }
        $text = $this->documents->extract($resume->original_file_path_cv);
        $this->validator->validate($text);
        $linkedin = $resume->original_file_path_linkedin
            ? $this->documents->extract($resume->original_file_path_linkedin, true) : null;
        $sources = [
            'version' => 1, 'resume_text' => $text, 'linkedin_text' => $linkedin,
            'github_url' => $context['github_url'], 'portfolio_url' => $context['portfolio_url'],
            'github_profile' => $this->github->fetch($context['github_url']),
            'additional_skills' => array_map(fn ($skill) => [
                'name' => $skill['name'], 'years' => isset($skill['years']) ? (int) $skill['years'] : null,
            ], $context['additional_skills']),
        ];
        if (mb_strlen(json_encode($sources, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) > config('resume.ollama.max_source_characters')) {
            throw new RuntimeException('The documents exceed the configured generation input limit.', 422);
        }

        return $sources;
    }
}
