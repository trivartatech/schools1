<?php

namespace App\Support;

use App\Models\School;
use Carbon\Carbon;

/**
 * Centralized formatter that respects each tenant's System Config:
 *   - Date Format   (DD/MM/YYYY, MM/DD/YYYY, YYYY-MM-DD, D MMM, YYYY)
 *   - Time Format   (h:mm A, H:mm, h:mm:ss A)
 *   - Timezone      (already applied globally by ResolveTenant middleware)
 *   - Currency      (school->currency, defaults to ₹)
 *
 * Usable from PHP (Format::date($x)) or Blade (@fdate($x), @ftime, @fdatetime, @fmoney).
 */
class Format
{
    public static function date($value, string $fallback = '—'): string
    {
        $carbon = self::toCarbon($value);
        return $carbon ? $carbon->format(self::dateFmt()) : ($value === null || $value === '' ? $fallback : (string) $value);
    }

    public static function time($value, string $fallback = '—'): string
    {
        if ($value === null || $value === '') return $fallback;

        if (is_string($value) && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
            try {
                return Carbon::parse('1970-01-01 ' . $value)->format(self::timeFmt());
            } catch (\Throwable $e) {
                return $value;
            }
        }

        $carbon = self::toCarbon($value);
        return $carbon ? $carbon->format(self::timeFmt()) : (string) $value;
    }

    public static function datetime($value, string $fallback = '—'): string
    {
        $carbon = self::toCarbon($value);
        return $carbon
            ? $carbon->format(self::dateFmt() . ', ' . self::timeFmt())
            : ($value === null || $value === '' ? $fallback : (string) $value);
    }

    public static function money($value, bool $fixed = false, ?string $symbol = null): string
    {
        $sym = $symbol ?? (self::school()?->currency ?? '₹');
        $num = (float) ($value ?? 0);
        return $sym . number_format($num, $fixed ? 2 : 0, '.', ',');
    }

    public static function dateFmt(): string
    {
        return self::school()?->dateFmt() ?? 'd/m/Y';
    }

    public static function timeFmt(): string
    {
        return self::school()?->timeFmt() ?? 'g:i A';
    }

    private static function school(): ?School
    {
        return app()->bound('current_school') ? app('current_school') : null;
    }

    private static function toCarbon($value): ?Carbon
    {
        if ($value === null || $value === '') return null;
        if ($value instanceof Carbon) return $value;
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
