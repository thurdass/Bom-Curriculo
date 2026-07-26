<?php

namespace App\Enums;

enum UserLanguageLevelEnum: string
{
    case BEGINNER = 'beginner';
    case INTERMEDIATE = 'intermediate';
    case ADVANCED = 'advanced';
    case FLUENT = 'fluent';
    case NATIVE = 'native';

    public static function toSelectArray(): array
    {
        return array_map(fn ($status) => [
            'value' => $status->value,
        ], self::cases());
    }
}
