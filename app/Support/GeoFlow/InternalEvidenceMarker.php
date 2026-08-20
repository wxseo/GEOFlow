<?php

namespace App\Support\GeoFlow;

final class InternalEvidenceMarker
{
    private const PATTERN = '/(?:\[\s*K\d+\s*\]|【\s*K\d+\s*】|\(\s*K\d+\s*\)|（\s*K\d+\s*）)/iu';

    public static function remove(string $content): string
    {
        $cleaned = preg_replace(self::PATTERN, '', $content) ?? $content;

        return preg_replace('/[ \t]+(?=[，。！？；：、,.!?;:])/u', '', $cleaned) ?? $cleaned;
    }

    /** @return list<string> */
    public static function extract(string $content): array
    {
        if (preg_match_all(self::PATTERN, $content, $matches) !== false) {
            return array_values($matches[0] ?? []);
        }

        return [];
    }
}
