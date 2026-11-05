<?php

namespace App\Services\Resume;

use RuntimeException;

final class ResumeScorePayload
{
    public static function schema(): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false, 'required' => ['score', 'suggestion'],
            'properties' => [
                'score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'suggestion' => ['type' => 'string', 'minLength' => 1],
            ],
        ];
    }

    public static function validate(array $payload): array
    {
        if (count($payload) !== 2 || ! is_int($payload['score'] ?? null) || $payload['score'] < 0 || $payload['score'] > 100
            || ! is_string($payload['suggestion'] ?? null) || trim($payload['suggestion']) === '') {
            throw new RuntimeException('Invalid resume score response.', 502);
        }

        return $payload;
    }
}
