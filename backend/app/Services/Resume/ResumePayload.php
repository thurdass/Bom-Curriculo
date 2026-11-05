<?php

namespace App\Services\Resume;

use InvalidArgumentException;
use stdClass;

/** The v1 canonical resume generation contract. */
final class ResumePayload
{
    public static function schema(): array
    {
        $text = ['type' => 'string'];
        $nullable = ['type' => ['string', 'null']];
        $date = $nullable + ['description' => 'YYYY, YYYY-MM or YYYY-MM-DD as known; never invent missing date parts.'];
        $object = fn (array $fields): array => [
            'type' => 'object', 'properties' => $fields,
            'required' => array_keys($fields), 'additionalProperties' => false,
        ];
        $list = fn (array $fields): array => ['type' => 'array', 'items' => $object($fields)];

        return $object([
            'score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            'professional_summary' => $nullable,
            'header' => $object([
                'name' => $nullable, 'headline' => $nullable + ['description' => 'Professional headline in the requested output language. Translate descriptive job/student wording; preserve technology names.'], 'email' => $nullable,
                'location' => $nullable, 'contacts' => $nullable, 'emails' => $nullable,
                'links' => ['type' => 'object', 'additionalProperties' => $text],
            ]),
            'experiences' => $list([
                'company' => $text, 'role' => $text, 'start' => $date, 'end' => $date,
                'description' => $nullable, 'is_actual' => ['type' => ['boolean', 'null']],
                'city' => $nullable, 'state' => $nullable, 'country' => $nullable,
            ]),
            'projects' => $list([
                'title' => $text,
                'start' => $nullable + ['description' => 'Explicit start date of this project only; null if absent. Never reuse education or employment dates.'],
                'end' => $nullable + ['description' => 'Explicit end date of this project only; null if absent. Never infer the current date.'],
                'technologies' => $nullable,
                'description' => $nullable + ['description' => 'Translate the project description into the requested output language, including English GitHub descriptions. Preserve facts and proper names.'],
                'url' => $nullable,
            ]),
            'qualifications' => $list([
                'type' => $text + ['enum' => ['elementary_education', 'high_school', 'extracurricular_course', 'technical_course', 'undergraduate_degree', 'postgraduate_degree', 'master_degree', 'doctorate_degree']],
                'institution' => $text, 'title' => $text, 'start' => $date, 'end' => $date,
                'is_coursing' => ['type' => 'boolean'],
            ]),
            'skills' => $list(['name' => $text, 'years' => ['type' => ['integer', 'null']]]),
            'languages' => $list([
                'level' => $text + ['enum' => ['beginner', 'intermediate', 'advanced', 'fluent', 'native']],
                'language' => $text,
            ]),
            'others' => ['type' => 'object'],
        ]);
    }

    public static function validate(array $payload): array
    {
        self::validateNode($payload, self::schema(), 'payload');

        return $payload;
    }

    /** Validate the deliberately small JSON Schema vocabulary used by schema(). */
    private static function validateNode(mixed $value, array $schema, string $path): void
    {
        $types = (array) $schema['type'];
        $type = match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_string($value) => 'string',
            $value instanceof stdClass => 'object',
            is_array($value) => array_is_list($value) ? 'array' : 'object',
            default => 'unsupported',
        };

        if (! in_array($type, $types, true)
            || (isset($schema['enum']) && ! in_array($value, $schema['enum'], true))
            || (isset($schema['minimum']) && $value < $schema['minimum'])
            || (isset($schema['maximum']) && $value > $schema['maximum'])) {
            throw new InvalidArgumentException("Invalid resume payload at {$path}.", 502);
        }

        if ($type === 'array') {
            foreach ($value as $index => $item) {
                self::validateNode($item, $schema['items'], "{$path}.{$index}");
            }
        }

        if ($type === 'object') {
            $fields = (array) $value;
            foreach ($schema['required'] ?? [] as $field) {
                if (! array_key_exists($field, $fields)) {
                    throw new InvalidArgumentException("Missing resume field at {$path}.{$field}.", 502);
                }
            }
            foreach ($fields as $field => $item) {
                $child = $schema['properties'][$field] ?? ($schema['additionalProperties'] ?? true);
                if ($child === false) {
                    throw new InvalidArgumentException("Unexpected resume field at {$path}.{$field}.", 502);
                }
                if (is_array($child)) {
                    self::validateNode($item, $child, "{$path}.{$field}");
                }
            }
        }
    }
}
