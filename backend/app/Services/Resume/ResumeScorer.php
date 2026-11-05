<?php

namespace App\Services\Resume;

use App\Models\User;
use App\Models\UserResume;

final class ResumeScorer
{
    public function __construct(private ResumeSourcePreparer $preparer, private ResumeScorePrompt $prompt, private OllamaClient $client) {}

    public function score(UserResume $resume, User $user): array
    {
        return $this->client->score($this->prompt->messages($this->preparer->prepare($resume, $user)), $resume->id);
    }
}
