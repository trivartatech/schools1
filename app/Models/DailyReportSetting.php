<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportSetting extends Model
{
    protected $fillable = [
        'school_id',
        'sections_enabled',
        'oversized_expense_threshold',
        'low_attendance_threshold_pct',
        'repeat_absent_days',
        'auto_send_time',
        'auto_send_enabled',
        'weekly_digest_enabled',
    ];

    protected $casts = [
        'sections_enabled'             => 'array',
        'oversized_expense_threshold'  => 'decimal:2',
        'low_attendance_threshold_pct' => 'integer',
        'repeat_absent_days'           => 'integer',
        'auto_send_enabled'            => 'boolean',
        'weekly_digest_enabled'        => 'boolean',
    ];

    public const ALL_SECTIONS = [
        'alerts', 'highlights', 'attendance', 'fees',
        'expenses', 'cash', 'admissions', 'events', 'outlook',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public static function forSchool(int $schoolId): self
    {
        return self::firstOrCreate(
            ['school_id' => $schoolId],
            ['sections_enabled' => self::ALL_SECTIONS],
        );
    }

    public function isSectionEnabled(string $section): bool
    {
        $enabled = $this->sections_enabled ?: self::ALL_SECTIONS;
        return in_array($section, $enabled, true);
    }
}
