<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        protected \App\Services\FeeService $feeService,
        protected \App\Services\DashboardDataService $dashboardData,
    ) {}

    /**
     * Show the appropriate dashboard based on user role.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $props = [];

        // Platform Super Admin
        if ($user->isSuperAdmin()) {
            $props['stats'] = [
                'total_organizations' => \App\Models\Organization::count(),
                'total_schools'       => \App\Models\School::count(),
                'total_users'         => \App\Models\User::count(),
            ];
        }

        // School Admin / Accountant / Driver / Teacher fallback — they need the school object on the page
        if ($user->isAdmin() || $user->isAccountant() || $user->isDriver() || $user->isTeacher() || (!$user->isSuperAdmin() && !$user->isStudent() && !$user->isParent())) {
            $props['school'] = app()->bound('current_school') ? app('current_school') : null;
        }

        // Student/Parent
        if ($user->isStudent() || $user->isParent()) {
            $students = collect();

            if ($user->isStudent()) {
                $student = \App\Models\Student::where('user_id', $user->id)
                    ->with([
                        'currentAcademicHistory.courseClass',
                        'currentAcademicHistory.section',
                        'feePayments',
                        'transportAllocation',
                        'hostelAllocation.bed.room',
                    ])
                    ->first();
                if ($student) $students->push($student);
            } else {
                $parent = \App\Models\StudentParent::where('user_id', $user->id)->first();
                if ($parent) {
                    $students = \App\Models\Student::where('parent_id', $parent->id)
                        ->with([
                            'currentAcademicHistory.courseClass',
                            'currentAcademicHistory.section',
                            'feePayments',
                            'transportAllocation',
                            'hostelAllocation.bed.room',
                        ])
                        ->get();
                }
            }

            $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
            $schoolId = $user->school_id;

            // Batch-load attendance counts for ALL students in one query each
            // instead of 2 queries × N students inside the map() loop below.
            $studentIds = $students->pluck('id')->all();
            $attTotals  = [];
            $attPresent = [];
            if ($academicYearId && !empty($studentIds)) {
                $allAtt = \App\Models\Attendance::whereIn('student_id', $studentIds)
                    ->where('academic_year_id', $academicYearId)
                    ->get(['student_id', 'status']);

                foreach ($allAtt as $row) {
                    $sid = $row->student_id;
                    $attTotals[$sid]  = ($attTotals[$sid]  ?? 0) + 1;
                    if (in_array($row->status, ['present', 'late', 'half_day'])) {
                        $attPresent[$sid] = ($attPresent[$sid] ?? 0) + 1;
                    }
                }
            }

            $portalData = $students->map(function ($student) use ($schoolId, $academicYearId, $attTotals, $attPresent) {
                // Pass pre-loaded relations — FeeService skips DB queries when provided
                $feeBalance = 0;
                if ($academicYearId) {
                    $summary = $this->feeService->getStudentFeeSummary(
                        $student,
                        $academicYearId,
                        $schoolId,
                        [
                            'payments'            => $student->feePayments,
                            'transportAllocation' => $student->transportAllocation,
                            'hostelAllocation'    => $student->hostelAllocation,
                        ]
                    );
                    $feeBalance = $summary['balance'];
                }

                // Use pre-aggregated attendance counts — zero extra queries
                $sid           = $student->id;
                $totalDays     = $attTotals[$sid]  ?? 0;
                $presentDays   = $attPresent[$sid] ?? 0;
                $attendancePerc= $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 100;

                return [
                    'id'                  => $student->id,
                    'name'                => $student->first_name . ' ' . $student->last_name,
                    'admission_no'        => $student->admission_no,
                    'class_name'          => $student->currentAcademicHistory?->courseClass?->name ?? 'N/A',
                    'section_name'        => $student->currentAcademicHistory?->section?->name ?? 'N/A',
                    'fee_balance'         => $feeBalance,
                    'attendance_percentage' => $attendancePerc,
                    'photo_url'           => $student->photo_url,
                ];
            });

            $props['students'] = $portalData;
            $props['is_parent'] = $user->isParent();
        }

        // ── Teacher Dashboard Data ──────────────────────────────────────
        if ($user->isTeacher()) {
            $schoolId = $user->school_id;
            $staff = $user->staff;

            if ($staff) {
                $days = [
                    1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
                    4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'
                ];

                $schedule = \App\Models\Timetable::where('school_id', $schoolId)
                    ->where('staff_id', $staff->id)
                    ->with(['courseClass', 'section', 'subject', 'period'])
                    ->get()
                    ->groupBy('day_of_week')
                    ->map(function ($dayItems) {
                        return $dayItems->sortBy(fn($item) => $item->period->order)->values()->map(fn($item) => [
                            'id' => $item->id,
                            'class' => $item->courseClass->name ?? '—',
                            'section' => $item->section->name ?? '—',
                            'subject' => $item->subject->name ?? '—',
                            'period' => $item->period->name ?? '—',
                            'time' => ($item->period->start_time ?? '') . ' - ' . ($item->period->end_time ?? ''),
                            'period_id' => $item->period_id,
                            'day_id' => $item->day_of_week,
                        ]);
                    });

                $props['teacher_schedule'] = [
                    'days' => $days,
                    'schedule' => $schedule
                ];
            }
        }

        // ── School Admin Dashboard Data ─────────────────────────────────
        // All admin-side data prep lives in DashboardDataService.
        if (!$user->isSuperAdmin() && !$user->isStudent() && !$user->isParent()) {
            $school = app()->bound('current_school') ? app('current_school') : null;
            $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
            if ($school) {
                $props['school_dashboard'] = $this->dashboardData->adminDashboard($school->id, $academicYearId, $user);
            }
        }

        return Inertia::render('Dashboard', $props);
    }
}
