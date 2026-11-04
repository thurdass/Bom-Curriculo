<?php

namespace App\Services\Resume;

/** Read/review projection; the original AI payload remains available unchanged. */
final class ResumeProjection
{
    public static function fromPayload(array $payload): array
    {
        if (array_key_exists('header', $payload)) {
            $header = (array) $payload['header'];
            $header['summary'] = $payload['professional_summary'] ?? null;
            $others = (array) ($payload['others'] ?? []);
            $others['score'] = $payload['score'] ?? null;

            return [
                'header' => $header,
                'experiences' => $payload['experiences'] ?? [],
                'projects' => $payload['projects'] ?? [],
                'qualifications' => $payload['qualifications'] ?? [],
                'skills' => $payload['skills'] ?? [],
                'languages' => $payload['languages'] ?? [],
                'others' => $others,
            ];
        }

        $personal = $payload['personal'] ?? [];
        $links = array_filter([
            'LinkedIn' => $personal['linkedin'] ?? null,
            'GitHub' => $personal['github'] ?? null,
            'Portfolio' => $personal['portfolio'] ?? null,
        ], fn ($value) => is_string($value) && $value !== '');

        return [
            'header' => [
                'name' => $personal['name'] ?? null,
                'headline' => $payload['target_role'] ?? null,
                'email' => $personal['email'] ?? null,
                'emails' => $personal['email'] ?? null,
                'contacts' => $personal['phone'] ?? null,
                'location' => implode(', ', array_filter((array) ($personal['location'] ?? []))),
                'links' => $links,
                'summary' => $payload['professional_summary'] ?? null,
            ],
            'experiences' => array_map(fn ($item) => array_merge($item, [
                'start' => $item['start_date'] ?? null, 'end' => $item['end_date'] ?? null,
                'is_actual' => $item['current'] ?? null,
            ]), $payload['experiences'] ?? []),
            'projects' => array_map(fn ($item) => array_merge($item, [
                'title' => $item['name'] ?? '', 'technologies' => implode(', ', $item['skills'] ?? []),
            ]), $payload['projects'] ?? []),
            'qualifications' => array_merge(
                array_map(fn ($item) => array_merge($item, [
                    'type' => null, 'title' => $item['degree'] ?? '',
                    'start' => $item['start_date'] ?? null, 'end' => $item['end_date'] ?? null,
                    'is_coursing' => $item['current'] ?? false,
                ]), $payload['education'] ?? []),
                array_map(fn ($item) => array_merge($item, [
                    'type' => 'extracurricular_course', 'start' => null,
                    'end' => $item['completion_date'] ?? null, 'is_coursing' => false,
                ]), $payload['courses'] ?? []),
            ),
            'skills' => array_map(fn ($item) => array_merge($item, ['name' => $item['title'] ?? '']), $payload['skills'] ?? []),
            'languages' => $payload['languages'] ?? [],
            'others' => [
                'certifications' => $payload['certifications'] ?? [],
                'additional_informations' => $payload['additional_informations'] ?? null,
            ],
        ];
    }
}
