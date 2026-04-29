<?php

namespace App\Utils;

class AiJsonParser
{
    public static function object(?string $raw): ?array
    {
        $cleaned = self::strip($raw);
        if ($cleaned === null) return null;

        $start = strpos($cleaned, '{');
        $end   = strrpos($cleaned, '}');
        if ($start === false || $end === false || $end <= $start) return null;

        $candidate = self::balanced(substr($cleaned, $start), '{', '}');
        if ($candidate === null) return null;

        $decoded = json_decode($candidate, true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function array(?string $raw): ?array
    {
        $cleaned = self::strip($raw);
        if ($cleaned === null) return null;

        $start = strpos($cleaned, '[');
        $end   = strrpos($cleaned, ']');
        if ($start === false || $end === false || $end <= $start) return null;

        $candidate = self::balanced(substr($cleaned, $start), '[', ']');
        if ($candidate === null) return null;

        $decoded = json_decode($candidate, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function strip(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') return null;

        $clean = preg_replace('/```(?:json)?\s*/i', '', $raw);
        $clean = preg_replace('/```/', '', $clean);

        return trim($clean);
    }

    private static function balanced(string $input, string $open, string $close): ?string
    {
        $depth   = 0;
        $inStr   = false;
        $escaped = false;

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $ch = $input[$i];

            if ($inStr) {
                if ($escaped) { $escaped = false; continue; }
                if ($ch === '\\') { $escaped = true; continue; }
                if ($ch === '"')  { $inStr = false; }
                continue;
            }

            if ($ch === '"') { $inStr = true; continue; }
            if ($ch === $open)  $depth++;
            if ($ch === $close) {
                $depth--;
                if ($depth === 0) {
                    return substr($input, 0, $i + 1);
                }
            }
        }

        return null;
    }
}
