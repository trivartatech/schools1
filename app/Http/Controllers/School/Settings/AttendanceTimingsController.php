<?php

namespace App\Http\Controllers\School\Settings;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Settings · Attendance Timings
 *
 * Per-school, per-role, per-day-bucket (weekday / weekend) configuration
 * for "is this a working day" + "after what time is a punch considered
 * late". Persisted on `schools.settings.attendance_timings` (JSON).
 *
 * The legacy `settings.late_threshold` key is kept untouched on save so
 * any consumer that still reads it (older mobile builds, etc.) keeps
 * the same behaviour. New consumers should call
 * `School::lateThresholdFor()` and `School::isWorkingDay()`.
 */
class AttendanceTimingsController extends Controller
{
    public function index()
    {
        $school   = School::findOrFail(app('current_school_id'));
        $settings = $school->settings ?? [];
        $timings  = $settings['attendance_timings'] ?? [];

        return Inertia::render('School/Settings/AttendanceTimings', [
            'school'   => $school->only(['id', 'name', 'code']),
            'settings' => [
                'late_threshold'    => $settings['late_threshold'] ?? null, // legacy display
                'weekend_days'      => $timings['weekend_days'] ?? [0],     // Sunday only by default
                'staff_weekday'     => $this->bucket($timings, 'staff',   'weekday', '09:30'),
                'staff_weekend'     => $this->bucket($timings, 'staff',   'weekend', '09:30', false),
                'student_weekday'   => $this->bucket($timings, 'student', 'weekday', '08:30'),
                'student_weekend'   => $this->bucket($timings, 'student', 'weekend', '08:30', false),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'weekend_days'              => 'array',
            'weekend_days.*'            => 'integer|between:0,6',
            'staff_weekday.working'     => 'boolean',
            'staff_weekday.late_after'  => ['required', 'date_format:H:i'],
            'staff_weekend.working'     => 'boolean',
            'staff_weekend.late_after'  => ['required', 'date_format:H:i'],
            'student_weekday.working'   => 'boolean',
            'student_weekday.late_after'=> ['required', 'date_format:H:i'],
            'student_weekend.working'   => 'boolean',
            'student_weekend.late_after'=> ['required', 'date_format:H:i'],
        ]);

        $school   = School::findOrFail(app('current_school_id'));
        $settings = $school->settings ?? [];

        $settings['attendance_timings'] = [
            'weekend_days' => array_values(array_unique(array_map('intval', $validated['weekend_days'] ?? [0]))),
            'staff' => [
                'weekday' => $this->normalize($validated['staff_weekday']  ?? []),
                'weekend' => $this->normalize($validated['staff_weekend']  ?? []),
            ],
            'student' => [
                'weekday' => $this->normalize($validated['student_weekday'] ?? []),
                'weekend' => $this->normalize($validated['student_weekend'] ?? []),
            ],
        ];

        // Keep the legacy single-key in sync with the staff weekday threshold so
        // older mobile clients reading `settings.late_threshold` continue to
        // behave correctly without a code change.
        $settings['late_threshold'] = $settings['attendance_timings']['staff']['weekday']['late_after'];

        $school->update(['settings' => $settings]);

        return back()->with('success', 'Attendance timings updated.');
    }

    /** Normalize one bucket payload (working bool + late_after HH:MM). */
    private function normalize(array $bucket): array
    {
        return [
            'working'    => (bool) ($bucket['working'] ?? true),
            'late_after' => $bucket['late_after'] ?? '09:30',
        ];
    }

    /** Read a bucket from existing settings with sensible defaults. */
    private function bucket(array $timings, string $role, string $key, string $defaultTime, bool $defaultWorking = true): array
    {
        $b = $timings[$role][$key] ?? [];
        return [
            'working'    => (bool) ($b['working'] ?? $defaultWorking),
            'late_after' => $b['late_after'] ?? $defaultTime,
        ];
    }
}
