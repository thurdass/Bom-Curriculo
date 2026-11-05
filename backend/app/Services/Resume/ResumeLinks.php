<?php

namespace App\Services\Resume;

final class ResumeLinks
{
    /** Preserve reported links over model guesses and remove repeated destinations. */
    public static function merge(array $links, array $reported = []): array
    {
        foreach ($reported as $label => $url) {
            foreach ($links as $key => $value) {
                if (mb_strtolower(trim((string) $key)) === mb_strtolower($label)) {
                    unset($links[$key]);
                }
            }
            $links[$label] = $url;
        }
        $seen = [];

        return array_filter($links, function ($url) use (&$seen) {
            if (! is_string($url) || trim($url) === '') {
                return false;
            }
            $key = trim($url);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        });
    }
}
