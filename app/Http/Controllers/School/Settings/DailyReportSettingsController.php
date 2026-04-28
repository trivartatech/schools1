<?php

namespace App\Http\Controllers\School\Settings;

use App\Http\Controllers\Controller;
use App\Models\AdminContact;
use App\Models\DailyReportSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DailyReportSettingsController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');
        $settings = DailyReportSetting::forSchool($schoolId);

        // Hand the admin numbers count to the page so we can warn if 0
        $adminContactsCount = AdminContact::where('school_id', $schoolId)->count();

        return Inertia::render('School/Settings/DailyReport', [
            'settings'             => [
                'sections_enabled'             => $settings->sections_enabled ?: DailyReportSetting::ALL_SECTIONS,
                'oversized_expense_threshold'  => (float) $settings->oversized_expense_threshold,
                'low_attendance_threshold_pct' => (int)   $settings->low_attendance_threshold_pct,
                'repeat_absent_days'           => (int)   $settings->repeat_absent_days,
                'auto_send_time'               => substr((string) $settings->auto_send_time, 0, 5),
                'auto_send_enabled'            => (bool)  $settings->auto_send_enabled,
                'weekly_digest_enabled'        => (bool)  $settings->weekly_digest_enabled,
            ],
            'all_sections'         => DailyReportSetting::ALL_SECTIONS,
            'admin_contacts_count' => $adminContactsCount,
        ]);
    }

    public function update(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'sections_enabled'              => ['required', 'array'],
            'sections_enabled.*'            => [Rule::in(DailyReportSetting::ALL_SECTIONS)],
            'oversized_expense_threshold'   => ['required', 'numeric', 'min:0'],
            'low_attendance_threshold_pct'  => ['required', 'integer', 'min:0', 'max:100'],
            'repeat_absent_days'            => ['required', 'integer', 'min:2', 'max:14'],
            'auto_send_time'                => ['required', 'date_format:H:i'],
            'auto_send_enabled'             => ['boolean'],
            'weekly_digest_enabled'         => ['boolean'],
        ]);

        $settings = DailyReportSetting::forSchool($schoolId);
        $settings->update([
            'sections_enabled'             => $validated['sections_enabled'],
            'oversized_expense_threshold'  => $validated['oversized_expense_threshold'],
            'low_attendance_threshold_pct' => $validated['low_attendance_threshold_pct'],
            'repeat_absent_days'           => $validated['repeat_absent_days'],
            'auto_send_time'               => $validated['auto_send_time'] . ':00',
            'auto_send_enabled'            => (bool) ($validated['auto_send_enabled'] ?? false),
            'weekly_digest_enabled'        => (bool) ($validated['weekly_digest_enabled'] ?? false),
        ]);

        return back()->with('success', 'Daily report settings updated.');
    }
}
