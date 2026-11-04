<?php

namespace App\Http\ApiRequests\Client\Resume;

use App\Http\ApiRequests\CustomRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FinishResumeRequest extends CustomRequest
{
    public function rules(): array
    {
        $textOrList = function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) && (! is_array($value) || count(array_filter($value, 'is_string')) !== count($value))) {
                $fail("The {$attribute} field must be text or a list of text values.");
            }
        };

        return [
            'user_resume_id' => ['required', 'uuid', Rule::exists('resume_analytics', 'user_resume_id')->where('user_id', $this->user()->id)],
            'header' => ['present', 'array'],
            'header.name' => ['nullable', 'string'],
            'header.headline' => ['nullable', 'string'],
            'header.summary' => ['nullable', 'string'],
            'header.location' => ['nullable', 'string'],
            'header.email' => ['nullable', 'string'],
            'header.emails' => ['nullable', $textOrList],
            'header.contacts' => ['nullable', $textOrList],
            'header.links' => ['array'],
            'header.links.*' => ['string'],
            'experiences' => ['array'],
            'experiences.*' => ['array'],
            'experiences.*.company' => ['present', 'nullable', 'string'],
            'experiences.*.role' => ['present', 'nullable', 'string'],
            'experiences.*.description' => ['nullable', 'string'],
            'experiences.*.start' => ['nullable', 'string'],
            'experiences.*.end' => ['nullable', 'string'],
            'experiences.*.is_actual' => ['nullable', 'boolean'],
            'experiences.*.city' => ['nullable', 'string'],
            'experiences.*.state' => ['nullable', 'string'],
            'experiences.*.country' => ['nullable', 'string'],
            'projects' => ['array'],
            'projects.*' => ['array'],
            'projects.*.title' => ['present', 'nullable', 'string'],
            'projects.*.description' => ['nullable', 'string'],
            'projects.*.start' => ['nullable', 'string'],
            'projects.*.end' => ['nullable', 'string'],
            'projects.*.url' => ['nullable', 'string'],
            'projects.*.technologies' => ['nullable', $textOrList],
            'qualifications' => ['array'],
            'qualifications.*' => ['array'],
            'qualifications.*.title' => ['present', 'nullable', 'string'],
            'qualifications.*.institution' => ['present', 'nullable', 'string'],
            'qualifications.*.start' => ['nullable', 'string'],
            'qualifications.*.end' => ['nullable', 'string'],
            'qualifications.*.is_coursing' => ['boolean'],
            'skills' => ['array'],
            'skills.*' => ['array'],
            'skills.*.name' => ['present', 'nullable', 'string'],
            'skills.*.years' => ['nullable', 'numeric'],
            'languages' => ['array'],
            'languages.*' => ['array'],
            'languages.*.language' => ['present', 'nullable', 'string'],
            'languages.*.level' => ['present', 'nullable', 'string'],
            'others' => ['array'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ((string) $this->route('resume') !== (string) $this->input('user_resume_id')) {
                $validator->errors()->add('user_resume_id', 'The route and resume ID must match.');
            }
        }];
    }
}
