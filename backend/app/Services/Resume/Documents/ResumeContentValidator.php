<?php

namespace App\Services\Resume\Documents;

use RuntimeException;

final class ResumeContentValidator
{
    public function validate(string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('empty', 422);
        }
        preg_match_all('/[\p{L}\p{N}_]+/u', mb_strtolower($text), $matches);
        $words = $matches[0];
        if (mb_strlen($text) < 100 || count($words) < 20) {
            throw new RuntimeException('too_short', 422);
        }
        if (count(array_unique($words)) / count($words) < 0.3) {
            throw new RuntimeException('low_content_diversity', 422);
        }
    }
}
