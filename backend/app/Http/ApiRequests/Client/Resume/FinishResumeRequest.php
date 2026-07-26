<?php

namespace App\Http\ApiRequests\Client\Resume;

use App\Http\ApiRequests\CustomRequest;

class FinishResumeRequest extends CustomRequest
{
    public function rules(): array
    {
        return [
            'user_resume_id' => ['required', 'exists:resume_analytics,user_resume_id'],
            'header' => ['required', 'array'],
            'experiences' => ['array'],
            'projects' => ['array'],
            'qualifications' => ['array'],
            'skills' => ['array'],
            'languages' => ['array'],
            'others' => ['array'],
        ];
    }
}
