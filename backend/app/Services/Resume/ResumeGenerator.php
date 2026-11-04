<?php

namespace App\Services\Resume;

use App\Models\User;
use App\Models\UserResume;

interface ResumeGenerator
{
    public function generate(UserResume $resume, User $user): array;
}
