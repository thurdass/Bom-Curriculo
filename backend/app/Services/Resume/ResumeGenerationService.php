<?php

namespace App\Services\Resume;

use App\Models\User;
use App\Models\UserResume;
use RuntimeException;

final class ResumeGenerationService implements ResumeGenerator
{
    public function __construct(
        private OllamaResumeGenerator $ollama,
        private PythonResumeGenerator $python,
    ) {}

    public function generate(UserResume $resume, User $user): array
    {
        $generator = match (config('resume.generation_driver')) {
            'ollama' => $this->ollama,
            'python' => $this->python,
            default => throw new RuntimeException('Invalid resume generation driver.', 503),
        };

        return $generator->generate($resume, $user);
    }
}
