<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected $feeService;

    public function __construct(\App\Services\FeeService $feeService)
    {
        $this->feeService = $feeService;
    }

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

        // School Admin / Accountant / Driver fallback
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

        // ── Enhanced School Admin Dashboard Data ──────────────────────────
        if (!$user->isSuperAdmin() && !$user->isStudent() && !$user->isParent()) {
            $school = app()->bound('current_school') ? app('current_school') : null;
            $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

            if ($school) {
                $schoolId = $school->id;
                $today        = now()->toDateString();
                $thisMonth    = now()->startOfMonth();
                $thisMonthEnd = now()->endOfMonth();

                // ── KPI Metrics ──────────────────────────────────────────
                // Scope student count to the selected academic year via histories,
                // so the dashboard reflects the year selector (empty before rollover
                // for a fresh year, year-over-year totals for past years).
                $totalStudents = \App\Models\Student::where('school_id', $schoolId)
                    ->where('status', 'active')
                    ->when($academicYearId, fn($q) => $q->whereHas('academicHistories',
                        fn($h) => $h->where('academic_year_id', $academicYearId)
                    ))
                    ->count();

                $newStudentsThisMonth = \App\Models\Student::where('school_id', $schoolId)
                    ->whereBetween('admission_date', [$thisMonth->toDateString(), $thisMonthEnd->toDateString()])
                    ->when($academicYearId, fn($q) => $q->whereHas('academicHistories',
                        fn($h) => $h->where('academic_year_id', $academicYearId)
                    ))
                    ->count();

                $totalStaff = \App\Models\Staff::where('school_id', $schoolId)
                    ->where('status', 'active')->count();

                $staffOnLeaveToday = \App\Models\Leave::where('school_id', $schoolId)
                    ->where('status', 'approved')
                    ->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today)
                    ->count();

                $totalClasses  = \App\Models\CourseClass::where('school_id', $schoolId)->count();
                $totalSections = \App\Models\Section::where('school_id', $schoolId)->forCurrentYear()->count();

                $todayFeeCollection = (float) \App\Models\FeePayment::where('school_id', $schoolId)
                    ->whereDate('payment_date', $today)
                    ->where('status', 'paid')
                    ->sum('amount_paid');

                $monthFeeCollection = (float) \App\Models\FeePayment::where('school_id', $schoolId)
                    ->whereBetween('payment_date', [$thisMonth->toDateString(), $thisMonthEnd->toDateString()])
                    ->where('status', 'paid')
                    ->sum('amount_paid');

                $schoolPending = $this->feeService->getSchoolPendingFees($schoolId, $academicYearId);
                $pendingFees = $schoolPending['pending_fees'];

                $activeRoutes = \App\Models\TransportRoute::where('school_id', $schoolId)
                    ->where('status', 'active')->count();

                $hostelOccupied = \App\Models\HostelStudent::where('school_id', $schoolId)
                    ->where('status', 'active')->count();

                $hostelCapacity = \App\Models\HostelRoom::whereHas('hostel', function ($q) use ($schoolId) {
                    $q->where('school_id', $schoolId);
                })->sum('capacity');

                // ── Today's Attendance Summary ────────────────────────────
                $todayAttn = \App\Models\Attendance::where('school_id', $schoolId)
                    ->where('date', $today)
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();

                $presentToday = ($todayAttn['present'] ?? 0) + ($todayAttn['late'] ?? 0) + ($todayAttn['half_day'] ?? 0);
                $absentToday  = $todayAttn['absent'] ?? 0;
                $totalMarked  = array_sum($todayAttn);

                // ── 6-Month Admission Trend ───────────────────────────────
                $admissionTrend = [];
                for ($i = 5; $i >= 0; $i--) {
                    $m = now()->subMonths($i);
                    $admissionTrend[] = [
                        'month' => $m->format('M Y'),
                        'count' => \App\Models\Student::where('school_id', $schoolId)
                            ->whereYear('admission_date', $m->year)
                            ->whereMonth('admission_date', $m->month)
                            ->count(),
                    ];
                }

                // ── 6-Month Fee Collection Chart ──────────────────────────
                $feeTrend = [];
                for ($i = 5; $i >= 0; $i--) {
                    $m = now()->subMonths($i);
                    $feeTrend[] = [
                        'month'  => $m->format('M Y'),
                        'amount' => (float) \App\Models\FeePayment::where('school_id', $schoolId)
                            ->whereYear('payment_date', $m->year)
                            ->whereMonth('payment_date', $m->month)
                            ->where('status', 'paid')
                            ->sum('amount_paid'),
                    ];
                }

                // ── Recent Admissions ─────────────────────────────────────
                $recentAdmissions = \App\Models\Student::where('school_id', $schoolId)
                    ->with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
                    ->orderByDesc('created_at')
                    ->limit(5)->get()
                    ->map(fn($s) => [
                        'id'           => $s->id,
                        'name'         => $s->first_name . ' ' . $s->last_name,
                        'admission_no' => $s->admission_no,
                        'class'        => $s->currentAcademicHistory?->courseClass?->name ?? '—',
                        'section'      => $s->currentAcademicHistory?->section?->name ?? '—',
                        'admitted_at'  => $s->admission_date ? \Carbon\Carbon::parse($s->admission_date)->format('d M Y') : '—',
                        'photo_url'    => $s->photo_url,
                    ]);

                // ── Recent Fee Payments ───────────────────────────────────
                $recentPayments = \App\Models\FeePayment::where('school_id', $schoolId)
                    ->with(['student', 'feeHead'])
                    ->orderByDesc('created_at')
                    ->limit(5)->get()
                    ->map(fn($p) => [
                        'id'         => $p->id,
                        'student'    => ($p->student?->first_name ?? '') . ' ' . ($p->student?->last_name ?? ''),
                        'receipt_no' => $p->receipt_no,
                        'amount'     => (float) $p->amount_paid,
                        'mode'       => $p->payment_mode,
                        'fee_head'   => $p->feeHead?->name ?? '—',
                        'paid_at'    => $p->payment_date?->format('d M Y') ?? '—',
                    ]);

                // ── Today's Visitors (Front Office) ───────────────────────
                $todayVisitors = \App\Models\VisitorLog::where('school_id', $schoolId)
                    ->whereDate('in_time', $today)
                    ->orderByDesc('in_time')
                    ->limit(5)->get()
                    ->map(fn($v) => [
                        'id'      => $v->id,
                        'name'    => $v->name,
                        'purpose' => $v->purpose,
                        'in_time' => $v->in_time?->format('h:i A') ?? '—',
                        'out'     => $v->out_time?->format('h:i A'),
                    ]);

                // ── Alerts: Pending Fee Students (from structure-based calculation) ──
                $pendingFeeStudents = $schoolPending['pending_fee_students'];

                // ── Alerts: Low Attendance Students (<75%) ────────────────
                $lowAttendance = [];
                if ($academicYearId) {
                    $lowAttendance = \App\Models\Attendance::where('school_id', $schoolId)
                        ->where('academic_year_id', $academicYearId)
                        ->selectRaw('student_id,
                            COUNT(*) as total,
                            SUM(CASE WHEN status IN ("present","late","half_day") THEN 1 ELSE 0 END) as present_count')
                        ->groupBy('student_id')
                        ->havingRaw('total > 0 AND (present_count / total * 100) < 75')
                        ->with('student')
                        ->orderByRaw('present_count / total ASC')
                        ->limit(5)->get()
                        ->map(fn($a) => [
                            'student'    => ($a->student?->first_name ?? '') . ' ' . ($a->student?->last_name ?? ''),
                            'percentage' => $a->total > 0 ? round(($a->present_count / $a->total) * 100, 1) : 0,
                        ])->toArray();
                }

                // ── Upcoming Exam Schedules ───────────────────────────────
                $upcomingExams = \App\Models\ExamScheduleSubject::whereHas('examSchedule', function ($q) use ($schoolId) {
                        $q->where('school_id', $schoolId);
                    })
                    ->whereNotNull('exam_date')
                    ->where('exam_date', '>=', $today)
                    ->with(['examSchedule.examType', 'subject'])
                    ->orderBy('exam_date')
                    ->limit(5)->get()
                    ->map(fn($es) => [
                        'exam'    => $es->examSchedule?->examType?->name ?? '—',
                        'subject' => $es->subject?->name ?? '—',
                        'date'    => \Carbon\Carbon::parse($es->exam_date)->format('d M Y'),
                    ]);

                // ── Calendar: Upcoming Holidays (60 days) ─────────────────
                $upcomingHolidays = \App\Models\Holiday::where('school_id', $schoolId)
                    ->where('date', '>=', $today)
                    ->where('date', '<=', now()->addDays(60)->toDateString())
                    ->orderBy('date')->get()
                    ->map(fn($h) => [
                        'id'    => $h->id,
                        'title' => $h->title,
                        'date'  => $h->date->format('Y-m-d'),
                        'end'   => $h->end_date?->format('Y-m-d'),
                        'type'  => $h->type ?? 'holiday',
                    ]);

                // ── Calendar: Upcoming Exam Dates (60 days) ───────────────
                $calendarExams = \App\Models\ExamScheduleSubject::whereHas('examSchedule', function ($q) use ($schoolId) {
                        $q->where('school_id', $schoolId);
                    })
                    ->whereNotNull('exam_date')
                    ->where('exam_date', '>=', $today)
                    ->where('exam_date', '<=', now()->addDays(60)->toDateString())
                    ->with(['examSchedule.examType', 'subject'])
                    ->orderBy('exam_date')->get()
                    ->map(fn($es) => [
                        'title' => ($es->examSchedule?->examType?->name ?? 'Exam') . ' – ' . ($es->subject?->name ?? ''),
                        'date'  => $es->exam_date,
                        'type'  => 'exam',
                    ]);

                // ── Latest Announcements ──────────────────────────────────
                $announcements = \App\Models\Announcement::where('school_id', $schoolId)
                    ->with('sender')
                    ->orderByDesc('created_at')
                    ->limit(5)->get()
                    ->map(fn($a) => [
                        'id'       => $a->id,
                        'title'    => $a->title,
                        'sender'   => $a->sender?->name ?? 'System',
                        'audience' => $a->audience_type,
                        'sent_at'  => $a->created_at->diffForHumans(),
                    ]);

                // ── Birthdays Today (Students) ────────────────────────────
                $birthdaysToday = \App\Models\Student::where('school_id', $schoolId)
                    ->where('status', 'active')
                    ->whereMonth('dob', now()->month)
                    ->whereDay('dob', now()->day)
                    ->limit(10)->get()
                    ->map(fn($s) => [
                        'name'  => $s->first_name . ' ' . $s->last_name,
                        'type'  => 'student',
                        'photo' => $s->photo_url,
                    ]);

                // ── Staff Absent Today (with names) ───────────────────────
                $absentStaffIds = \App\Models\Leave::where('school_id', $schoolId)
                    ->where('status', 'approved')
                    ->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today)
                    ->pluck('user_id');

                $absentStaffList = \App\Models\Staff::where('school_id', $schoolId)
                    ->whereIn('user_id', $absentStaffIds)
                    ->with(['user', 'designation'])
                    ->limit(8)->get()
                    ->map(fn($s) => [
                        'name'        => $s->user?->name ?? '—',
                        'designation' => $s->designation?->name ?? '—',
                        'photo'       => $s->photo_url ?? null,
                    ]);

                // ── Attendance Donut Breakdown ────────────────────────────
                $attendanceDonut = [
                    'present'  => ($todayAttn['present']  ?? 0),
                    'absent'   => ($todayAttn['absent']   ?? 0),
                    'late'     => ($todayAttn['late']     ?? 0),
                    'half_day' => ($todayAttn['half_day'] ?? 0),
                    'total'    => array_sum($todayAttn),
                ];

                // ── Fee Progress (Month) ──────────────────────────────────
                $totalFeeDue = (float) \App\Models\FeePayment::where('school_id', $schoolId)
                    ->whereBetween('payment_date', [$thisMonth->toDateString(), $thisMonthEnd->toDateString()])
                    ->sum('amount_due');

                $feeProgress = [
                    'collected' => $monthFeeCollection,
                    'due'       => $totalFeeDue > 0 ? $totalFeeDue : $monthFeeCollection,
                    'pct'       => $totalFeeDue > 0 ? min(100, round(($monthFeeCollection / $totalFeeDue) * 100)) : 100,
                ];

                // ── Next Exam Countdown ───────────────────────────────────
                $nextExamSubject = \App\Models\ExamScheduleSubject::whereHas('examSchedule', function ($q) use ($schoolId) {
                        $q->where('school_id', $schoolId);
                    })
                    ->whereNotNull('exam_date')
                    ->where('exam_date', '>=', $today)
                    ->with(['examSchedule.examType'])
                    ->orderBy('exam_date')
                    ->first();

                $nextExam = null;
                if ($nextExamSubject) {
                    $daysLeft = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($nextExamSubject->exam_date)->startOfDay());
                    $nextExam = [
                        'title'     => $nextExamSubject->examSchedule?->examType?->name ?? 'Exam',
                        'date'      => \Carbon\Carbon::parse($nextExamSubject->exam_date)->format('d M Y'),
                        'days_left' => (int) $daysLeft,
                    ];
                }

                // ── Pending Edit Requests ─────────────────────────────────
                $pendingEditCount = \App\Models\EditRequest::where('school_id', $schoolId)
                    ->where('status', 'pending')
                    ->count();

                $pendingEditList = \App\Models\EditRequest::where('school_id', $schoolId)
                    ->where('status', 'pending')
                    ->with(['user', 'requestable'])
                    ->orderByDesc('created_at')
                    ->limit(5)->get()
                    ->map(fn($r) => [
                        'id'       => $r->id,
                        'name'     => $r->user?->name ?? '—',
                        'type'     => class_basename($r->requestable_type ?? ''),
                        'fields'   => is_array($r->requested_changes) ? implode(', ', array_keys($r->requested_changes)) : '—',
                        'ago'      => $r->created_at->diffForHumans(),
                    ]);

                // ── Class-wise Attendance Today ───────────────────────────
                $classAttendance = \App\Models\Attendance::where('attendances.school_id', $schoolId)
                    ->where('attendances.date', $today)
                    ->join('course_classes as cc', 'attendances.class_id', '=', 'cc.id')
                    ->selectRaw('cc.name as class_name,
                        COUNT(*) as total,
                        SUM(CASE WHEN attendances.status IN ("present","late","half_day") THEN 1 ELSE 0 END) as present_count')
                    ->groupBy('cc.id', 'cc.name')
                    ->orderBy('cc.name')
                    ->limit(8)->get()
                    ->map(fn($r) => [
                        'class'   => $r->class_name,
                        'present' => (int) $r->present_count,
                        'total'   => (int) $r->total,
                        'pct'     => $r->total > 0 ? round(($r->present_count / $r->total) * 100) : 0,
                    ]);

                $props['school_dashboard'] = [
                    'kpi' => [
                        'total_students'     => $totalStudents,
                        'new_students_month' => $newStudentsThisMonth,
                        'total_staff'        => $totalStaff,
                        'staff_on_leave'     => $staffOnLeaveToday,
                        'total_classes'      => $totalClasses,
                        'total_sections'     => $totalSections,
                        'today_fee'          => $todayFeeCollection,
                        'month_fee'          => $monthFeeCollection,
                        'pending_fees'       => $pendingFees,
                        'active_routes'      => $activeRoutes,
                        'hostel_occupied'    => $hostelOccupied,
                        'hostel_capacity'    => $hostelCapacity,
                        'present_today'      => $presentToday,
                        'absent_today'       => $absentToday,
                        'attendance_marked'  => $totalMarked,
                    ],
                    'admission_trend'      => $admissionTrend,
                    'fee_trend'            => $feeTrend,
                    'recent_admissions'    => $recentAdmissions,
                    'recent_payments'      => $recentPayments,
                    'today_visitors'       => $todayVisitors,
                    'pending_fee_students' => $pendingFeeStudents,
                    'low_attendance'       => $lowAttendance,
                    'upcoming_exams'       => $upcomingExams,
                    'upcoming_holidays'    => $upcomingHolidays,
                    'calendar_exams'       => $calendarExams,
                    'announcements'        => $announcements,
                    // ── New additions ──
                    'birthdays_today'      => $birthdaysToday,
                    'absent_staff'         => $absentStaffList,
                    'attendance_donut'     => $attendanceDonut,
                    'fee_progress'         => $feeProgress,
                    'next_exam'            => $nextExam,
                    'pending_edit_count'   => $pendingEditCount,
                    'pending_edit_list'    => $pendingEditList,
                    'class_attendance'     => $classAttendance,
                    'admin_name'           => $user->name,
                ];
            }
        }

        return Inertia::render('Dashboard', $props);
    }
}

