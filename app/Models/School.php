<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'slug', 'code',
        'logo', 'favicon', 'board', 'affiliation_no', 'udise_code',
        'address', 'city', 'state', 'pincode', 'phone', 'email',
        'website', 'principal_name', 'timezone', 'currency',
        'language', 'status', 'features', 'settings',
        'geo_fence_lat', 'geo_fence_lng', 'geo_fence_radius',
    ];

    protected $casts = [
        'features' => 'array',
        'settings' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function academicYears()
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function currentAcademicYear()
    {
        return $this->hasOne(AcademicYear::class)->where('is_current', true);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function students()
    {
        return $this->hasMany(User::class)->where('user_type', 'student');
    }

    /**
     * Check if a given feature is enabled for this school.
     */
    public function hasFeature(string $feature): bool
    {
        return isset($this->features[$feature]) && $this->features[$feature] === true;
    }

    /** PHP date() format string from the admin's system-config date_format setting. */
    public function dateFmt(): string
    {
        return match($this->settings['date_format'] ?? '') {
            'DD/MM/YYYY'  => 'd/m/Y',
            'MM/DD/YYYY'  => 'm/d/Y',
            'YYYY-MM-DD'  => 'Y-m-d',
            'D MMM, YYYY' => 'j M, Y',
            default        => 'd/m/Y',
        };
    }

    /** PHP date() format string from the admin's system-config time_format setting. */
    public function timeFmt(): string
    {
        return match($this->settings['time_format'] ?? '') {
            'h:mm A'    => 'g:i A',
            'H:mm'      => 'H:i',
            'h:mm:ss A' => 'g:i:s A',
            default      => 'g:i A',
        };
    }

    /**
     * Default rows-per-page from System Config. Clamped to [5, 100] to
     * match the SystemConfig form validation. Use with paginate($school->pageLength()).
     */
    public function pageLength(int $fallback = 20): int
    {
        $n = (int) ($this->settings['page_length'] ?? $fallback);
        return max(5, min(100, $n));
    }

    // ── Attendance timings ───────────────────────────────────────────────
    // Settings shape (`settings.attendance_timings`):
    //   weekend_days: int[]   — day-of-week numbers (0 = Sunday … 6 = Saturday)
    //   staff:    { weekday: { working: bool, late_after: 'HH:MM' },
    //               weekend: { working: bool, late_after: 'HH:MM' } }
    //   student:  { weekday: { working: bool, late_after: 'HH:MM' },
    //               weekend: { working: bool, late_after: 'HH:MM' } }
    //
    // Both helpers fall back to the legacy `settings.late_threshold` key
    // (and to a sensible default) if the new structure is absent, so
    // existing schools keep working without a settings update.

    /** Returns 'weekday' or 'weekend' for the given moment. */
    protected function dayBucket(?\Carbon\Carbon $when = null): string
    {
        $when = $when ?? now();
        $weekendDays = $this->settings['attendance_timings']['weekend_days'] ?? [0]; // Sunday default
        return in_array((int) $when->dayOfWeek, array_map('intval', (array) $weekendDays), true)
            ? 'weekend'
            : 'weekday';
    }

    /**
     * HH:MM after which a punch / mark is considered late.
     *
     * @param string $for 'staff' | 'student'
     */
    public function lateThresholdFor(string $for, ?\Carbon\Carbon $when = null): string
    {
        $bucket = $this->dayBucket($when);
        $configured = $this->settings['attendance_timings'][$for][$bucket]['late_after'] ?? null;
        $legacy     = $this->settings['late_threshold'] ?? null;
        $default    = $for === 'student' ? '08:30' : '09:30';
        return $configured ?: ($legacy ?: $default);
    }

    /** True when today (or `$when`) is configured as a working day for the role. */
    public function isWorkingDay(string $for, ?\Carbon\Carbon $when = null): bool
    {
        $bucket = $this->dayBucket($when);
        $val = $this->settings['attendance_timings'][$for][$bucket]['working'] ?? null;
        if ($val === null) {
            // No new structure yet: weekday treated as working, weekend as off.
            return $bucket === 'weekday';
        }
        return (bool) $val;
    }

    /**
     * Apply the student late threshold to a requested status. Used by the QR
     * scan paths (web + mobile) so that hitting "Present" past the cutoff
     * silently records 'late' instead.
     *
     * Only auto-promotes 'present' → 'late'. Explicit picks ('absent', 'late',
     * 'half_day', 'leave') pass through unchanged so a staffer's deliberate
     * choice always wins.
     */
    public function resolveStudentAttendanceStatus(string $requested, ?\Carbon\Carbon $when = null): string
    {
        if ($requested !== 'present') return $requested;

        $when = $when ?? now();
        $threshold = $this->lateThresholdFor('student', $when);
        $time = \Carbon\Carbon::parse($when->format('H:i:s'));

        return $time->gt(\Carbon\Carbon::parse($threshold)) ? 'late' : 'present';
    }
}
