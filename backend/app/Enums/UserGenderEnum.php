<?php

namespace App\Enums;

enum UserGenderEnum: string
{
    case MALE = 'male';
    case FEMALE = 'female';
    case ANOTHER = 'another';

    public static function toSelectArray(): array
    {
        return array_map(fn ($status) => [
            'value' => $status->value,
        ], self::cases());
    }
}
