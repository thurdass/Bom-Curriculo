<?php

namespace App\Services\Resume;

use JsonException;
use RuntimeException;
use stdClass;

final class JsonObject
{
    public static function decode(string $json): array
    {
        try {
            $object = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Service returned invalid JSON.', 502, $error);
        }
        if (! $object instanceof stdClass) {
            throw new RuntimeException('Service returned an invalid response.', 502);
        }

        return array_map(self::normalize(...), get_object_vars($object));
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $fields = get_object_vars($value);
            if (array_is_list($fields)) {
                return $value;
            }
            $value = $fields;
        }

        return is_array($value) ? array_map(self::normalize(...), $value) : $value;
    }
}
