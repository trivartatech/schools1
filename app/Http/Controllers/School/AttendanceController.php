<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CourseClass;
use App\Models\Section;
use App\Models\StudentAcademicHistory;
use App\Services\NotificationService;
use App\Services\TeacherScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    /**
     * Show the attendance marking page (get students for a class/section/date).
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->can('create_attendance') && !$user->isSchoolManagement(), 403, 'You do not have permission to mark attendance.');

        $schoolId      = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        // ── Teacher scope: restrict class dropdown to assigned classes only ──────
        $scope = app(TeacherScopeService::class)->for($user);
        $classQuery = CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name');
        if ($scope->restricted && $scope->classIds->isNotEmpty()) {
            $classQuery->whereIn('id', $scope->classIds);
        }
        $classes  = $classQuery->get();
        $sections = [];
        $students = [];
        $existing = [];

        $selectedClassId   = $request->get('class_id');
        $selectedSectionId = $request->get('section_id');
        $selectedDate      = $request->get('date', now()->format('Y-m-d'));

        if ($selectedClassId) {
            $sections = Section::where('school_id', $schoolId)
                ->where('course_class_id', $selectedClassId)
                ->forCurrentYear()
                ->orderBy('sort_order')
                ->get();
        }

        if ($selectedClassId && $academicYearId) {
            // Get students enrolled in this class+section for the current year
            $historyQuery = StudentAcademicHistory::with('student')
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->where('class_id', $selectedClassId)
                ->where('status', 'current');

            if ($selectedSectionId) {
                $historyQuery->where('section_id', $selectedSectionId);
            }

            $students = $historyQuery->orderBy('roll_no')
                ->get()
                ->filter(fn($h) => $h->student !== null)
                ->map(fn($h) => [
                    'id'           => $h->student->id,
                    'name'         => $h->student->first_name . ' ' . $h->student->last_name,
                    'roll_no'      => $h->roll_no,        // roll_no lives on academic_histories, not students
                    'admission_no' => $h->student->admission_no,
                    'photo_url'    => $h->student->photo_url,
                ]);

            // Get existing attendance for this date (MySQL DATE column uses Y-m-d)
            $lookupDate = \Carbon\Carbon::parse($selectedDate)->toDateString();
            $existing = Attendance::where('school_id', $schoolId)
                ->where('date', $lookupDate)
                ->where('class_id', $selectedClassId)
                ->when($selectedSectionId, fn($q) => $q->where('section_id', $selectedSectionId))
                ->get()
                ->keyBy('student_id')
                ->toArray();
        }

        return Inertia::render('School/Attendance/Index', [
            'classes'           => $classes,
            'sections'          => $sections,
            'students'          => $students,
            'existingAttendance'=> $existing,
            'selectedClassId'   => (int) $selectedClassId,
            'selectedSectionId' => (int) $selectedSectionId,
            'selectedDate'      => $selectedDate,
        ]);
    }

    /**
     * Bulk save attendance records for a class+section+date.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->can('create_attendance') && !$user->isSchoolManagement(), 403, 'You do not have permission to mark attendance.');

        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $request->validate([
            'class_id'   => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_id' => ['nullable', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'date'       => 'required|date',
            'attendance' => 'required|array',
            'attendance.*.student_id' => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'attendance.*.status'     => 'required|in:present,absent,late,half_day,leave,holiday',
            'attendance.*.remarks'    => 'nullable|string|max:255',
        ]);

        $date      = \Carbon\Carbon::parse($request->date)->toDateString();
        $classId   = $request->class_id;
        $sectionId = $request->section_id;

        DB::transaction(function () use ($request, $schoolId, $academicYearId, $classId, $sectionId, $date) {
            foreach ($request->attendance as $row) {
                Attendance::updateOrCreate(
                    [
                        'school_id'  => $schoolId,
                        'student_id' => $row['student_id'],
                        'date'       => $date,
                    ],
                    [
                        'academic_year_id' => $academicYearId,
                        'class_id'         => $classId,
                        'section_id'       => $sectionId,
                        'status'           => $row['status'],
                        'remarks'          => $row['remarks'] ?? null,
                        'marked_by'        => auth()->id(),
                    ]
                );
            }
        });

        // Notifications now default to ON for non-Present statuses. The school
        // admin can opt-out per request (send_notifications=false) or globally
        // disable via settings.notifications_v2.attendance_auto_notify=false.
        $school              = app('current_school');
        $autoNotify          = $school->settings['notifications_v2']['attendance_auto_notify'] ?? true;
        $shouldSendDefault   = $autoNotify;
        $shouldSend          = $request->has('send_notifications')
            ? $request->boolean('send_notifications')
            : $shouldSendDefault;

        $notificationsSent = 0;
        if ($shouldSend) {
            try {
                $notificationService = new NotificationService($school);
                $notifyAll = $school->settings['notifications_v2']['attendance_notify_all'] ?? false;

                $studentIds = collect($request->attendance)->pluck('student_id');
                $students = \App\Models\Student::whereIn('id', $studentIds)
                    ->with(['studentParent', 'currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
                    ->get()
                    ->keyBy('id');

                foreach ($request->attendance as $row) {
                    // Skip present students unless "Notify All" is enabled in school settings
                    if ($row['status'] === 'present' && !$notifyAll) continue;

                    $student = $students->get($row['student_id']);
                    if ($student) {
                        try {
                            $notificationService->notifyAttendance($student, $row['status']);
                            $notificationsSent++;
                        } catch (\Throwable $e) {
                            Log::warning("Attendance notification failed for student {$student->id}: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Attendance notification batch failed: ' . $e->getMessage());
            }
        }

        $dateFormatted = \Carbon\Carbon::parse($date)->format('d M Y');
        $message = "Attendance saved for {$dateFormatted}!";
        if ($request->boolean('send_notifications')) {
            $message .= " Notifications triggered for {$notificationsSent} students.";
        }

        return back()->with('success', $message);
    }

    /**
     * Attendance report: monthly summary per student.
     */
    public function report(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->can('view_attendance') && !$user->can('view_own_attendance'), 403, 'Access denied.');

        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $parentStudentIds = null;
        if ($user->isParent()) {
            $parent = \App\Models\StudentParent::where('user_id', $user->id)->first();
            $parentStudentIds = $parent ? $parent->students()->pluck('id')->toArray() : [];
            // If parent has no mapped children, ensure they see no classes/reports
            if (empty($parentStudentIds)) {
                $parentStudentIds = [-1]; // impossible ID
            }
        } elseif ($user->isStudent()) {
            $parentStudentIds = $user->student ? [$user->student->id] : [-1];
        }

        // ── Teacher scope for report page ───────────────────────────────────
        $teacherScope = app(TeacherScopeService::class)->for($user);

        $classQuery = CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name');
        if ($parentStudentIds !== null) {
            $allowedClassIds = StudentAcademicHistory::where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->whereIn('student_id', $parentStudentIds)
                ->where('status', 'current')
                ->pluck('class_id')
                ->toArray();
            $classQuery->whereIn('id', $allowedClassIds);
        } elseif ($teacherScope->restricted && $teacherScope->classIds->isNotEmpty()) {
            $classQuery->whereIn('id', $teacherScope->classIds);
        }
        $classes = $classQuery->get();

        $sections = [];
        $report   = [];

        $selectedClassId   = $request->get('class_id');
        $selectedSectionId = $request->get('section_id');
        $selectedMonth     = $request->get('month', now()->format('Y-m'));

        // Guard against invalid month format before explode()
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
            $selectedMonth = now()->format('Y-m');
        }

        if ($selectedClassId) {
            $sections = Section::where('school_id', $schoolId)
                ->where('course_class_id', $selectedClassId)
                ->forCurrentYear()
                ->orderBy('sort_order')->get();
        }

        $daysInMonth = 0;
        $summary     = ['total_students' => 0, 'avg_present' => 0, 'total_absent' => 0, 'total_late' => 0];

        if ($selectedClassId && $academicYearId) {
            [$year, $month] = explode('-', $selectedMonth);
            $from  = "{$year}-{$month}-01";
            $monthDate   = \Carbon\Carbon::parse($from);
            $to          = $monthDate->copy()->endOfMonth()->toDateString();
            $daysInMonth = $monthDate->daysInMonth;

            $historyQuery = StudentAcademicHistory::with('student')
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->where('class_id', $selectedClassId)
                ->where('status', 'current');

            if ($parentStudentIds !== null) {
                $historyQuery->whereIn('student_id', $parentStudentIds);
            }

            if ($selectedSectionId) {
                $historyQuery->where('section_id', $selectedSectionId);
            }

            $histories = $historyQuery->orderBy('roll_no')->orderBy('id')->get();

            // Fetch all attendance records for the month (day-level)
            $records = Attendance::where('school_id', $schoolId)
                ->where('class_id', $selectedClassId)
                ->when($selectedSectionId, fn($q) => $q->where('section_id', $selectedSectionId))
                ->where('date', '>=', $from)
                ->where('date', '<=', $to)
                ->get()
                ->groupBy('student_id');

            $totalPresent = 0;
            $markedDays   = 0;

            foreach ($histories as $h) {
                if ($h->student === null) continue;
                $sid    = $h->student->id;
                $rows   = $records->get($sid, collect());
                $days   = [];
                $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'half_day' => 0, 'leave' => 0, 'working_days' => 0];

                foreach ($rows as $rec) {
                    $day = (int) \Carbon\Carbon::parse($rec->date)->day;
                    $days[$day] = $rec->status;
                    if (isset($counts[$rec->status])) {
                        $counts[$rec->status]++;
                    }
                    $counts['working_days']++;
                }

                $totalPresent         += $counts['present'];
                $summary['total_absent'] += $counts['absent'];
                $summary['total_late']   += $counts['late'];
                $markedDays           += $counts['working_days'];

                $report[] = [
                    'student_id' => $sid,
                    'name'       => $h->student->first_name . ' ' . $h->student->last_name,
                    'roll_no'    => $h->roll_no,
                    'days'       => $days,
                    'counts'     => $counts,
                ];
            }

            $summary['total_students'] = count($report);
            $summary['avg_present']    = $summary['total_students'] > 0 && $markedDays > 0
                ? round($totalPresent / max(1, $markedDays / max(1, $summary['total_students'])))
                : 0;
        }

        return Inertia::render('School/Attendance/Report', [
            'classes'           => $classes,
            'sections'          => $sections,
            'report'            => $report,
            'daysInMonth'       => $daysInMonth,
            'summary'           => $summary,
            'selectedClassId'   => (int) $selectedClassId,
            'selectedSectionId' => (int) $selectedSectionId,
            'selectedMonth'     => $selectedMonth,
        ]);
    }

    /**
     * Attendance forecast: 14-day history + 7-day projected trend.
     */
    public function forecast(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->can('view_attendance') && !$user->isSchoolManagement(), 403);

        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $classId        = $request->get('class_id');
        $sectionId      = $request->get('section_id');

        $classes  = CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get(['id', 'name']);
        $sections = $classId
            ? Section::where('school_id', $schoolId)->where('course_class_id', $classId)->forCurrentYear()->orderBy('sort_order')->get(['id', 'name'])
            : collect();

        $enrolledCount = StudentAcademicHistory::where('school_id', $schoolId)
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->when($classId,   fn($q) => $q->where('class_id',   $classId))
            ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
            ->where('status', 'current')
            ->count();

        $from = now()->subDays(29)->toDateString();
        $to   = now()->toDateString();

        $records = Attendance::where('school_id', $schoolId)
            ->when($classId,   fn($q) => $q->where('class_id',   $classId))
            ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->selectRaw('date, status, COUNT(*) as cnt')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get();

        // Build per-date buckets
        $byDate = [];
        foreach ($records as $r) {
            $d = (string) $r->date;
            if (!isset($byDate[$d])) {
                $byDate[$d] = ['present' => 0, 'absent' => 0, 'late' => 0, 'half_day' => 0, 'leave' => 0];
            }
            $byDate[$d][$r->status] = (int) $r->cnt;
        }

        $historical = [];
        $denom = max(1, $enrolledCount);
        foreach ($byDate as $date => $c) {
            $total   = array_sum($c);
            $present = $c['present'] + (int) round($c['late'] * 0.5) + (int) round($c['half_day'] * 0.5);
            $historical[] = [
                'date'    => $date,
                'rate'    => $total > 0 ? round($present / $denom * 100, 1) : null,
                'present' => $c['present'],
                'absent'  => $c['absent'],
                'total'   => $total,
            ];
        }

        // Simple linear-regression forecast over last 7 data points
        $forecast = [];
        $rates    = array_filter(array_column(array_slice($historical, -7), 'rate'));
        $n        = count($rates);

        if ($n >= 3) {
            $rates  = array_values($rates);
            $xMean  = ($n - 1) / 2;
            $yMean  = array_sum($rates) / $n;
            $num    = 0;
            $den    = 0;
            foreach ($rates as $i => $y) {
                $num += ($i - $xMean) * ($y - $yMean);
                $den += ($i - $xMean) ** 2;
            }
            $slope     = $den > 0 ? $num / $den : 0;
            $intercept = $yMean - $slope * $xMean;

            for ($i = 1; $i <= 7; $i++) {
                $forecast[] = [
                    'date'           => now()->addDays($i)->toDateString(),
                    'projected_rate' => round(max(0, min(100, $intercept + $slope * ($n - 1 + $i))), 1),
                ];
            }
        }

        $allRates = array_filter(array_column($historical, 'rate'));
        $avgRate  = count($allRates) > 0 ? round(array_sum($allRates) / count($allRates), 1) : null;

        return Inertia::render('School/Attendance/Forecast', [
            'historical'        => $historical,
            'forecast'          => $forecast,
            'avg_rate'          => $avgRate,
            'classes'           => $classes,
            'sections'          => $sections,
            'selected_class_id' => $classId ? (int) $classId : null,
            'selected_section_id' => $sectionId ? (int) $sectionId : null,
        ]);
    }

    /**
     * Date-wise attendance report: each row is a calendar date with counts + student detail.
     */
    public function dateWise(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->can('view_attendance') && !$user->isSchoolManagement(), 403, 'Access denied.');

        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $scope = app(TeacherScopeService::class)->for($user);
        $classQuery = CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name');
        if ($scope->restricted && $scope->classIds->isNotEmpty()) {
            $classQuery->whereIn('id', $scope->classIds);
        }
        $classes = $classQuery->get(['id', 'name']);

        $classId   = $request->get('class_id');
        $sectionId = $request->get('section_id');
        $from      = $request->get('from', now()->startOfMonth()->toDateString());
        $to        = $request->get('to', now()->toDateString());

        // Cap range at 92 days
        $fromDate = \Carbon\Carbon::parse($from);
        $toDate   = \Carbon\Carbon::parse($to);
        if ($fromDate->diffInDays($toDate) > 92) {
            $from     = $toDate->copy()->subDays(91)->toDateString();
            $fromDate = \Carbon\Carbon::parse($from);
        }

        $sections = $classId
            ? Section::where('school_id', $schoolId)->where('course_class_id', $classId)->forCurrentYear()->orderBy('sort_order')->get(['id', 'name'])
            : collect();

        // Enrolled count for denominator
        if ($classId && $academicYearId) {
            $enrolledCount = StudentAcademicHistory::where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->where('class_id', $classId)
                ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
                ->where('status', 'current')
                ->count();
        } else {
            $enrolledCount = \App\Models\Student::where('school_id', $schoolId)->where('status', 'active')->enrolledInYear($academicYearId)->count();
        }

        // Normalize date helper — fixes "2026-04-10 00:00:00" datetime values from DB
        $normDate = fn($d) => \Carbon\Carbon::parse($d)->toDateString();

        // Aggregate counts per date+status
        $records = Attendance::where('school_id', $schoolId)
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->when($classId,   fn($q) => $q->where('class_id',   $classId))
            ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
            ->whereBetween('date', [$from, $to])
            ->select('date', 'status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('date', 'status')
            ->get()
            ->groupBy(fn($r) => $normDate($r->date))
            ->sortKeysDesc();

        // Class-wise breakdown per date (for expandable dropdown)
        $classBreakdownRaw = Attendance::where('school_id', $schoolId)
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->when($classId,   fn($q) => $q->where('class_id',   $classId))
            ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
            ->whereBetween('date', [$from, $to])
            ->select('date', 'class_id', 'status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('date', 'class_id', 'status')
            ->get()
            ->groupBy(fn($r) => $normDate($r->date))
            ->map(fn($dateRows) =>
                $dateRows->groupBy('class_id')->map(fn($classRows, $cid) => [
                    'class_id' => (int) $cid,
                    'present'  => (int) ($classRows->where('status', 'present')->first()?->cnt  ?? 0),
                    'absent'   => (int) ($classRows->where('status', 'absent')->first()?->cnt   ?? 0),
                    'late'     => (int) ($classRows->where('status', 'late')->first()?->cnt     ?? 0),
                    'half_day' => (int) ($classRows->where('status', 'half_day')->first()?->cnt ?? 0),
                    'leave'    => (int) ($classRows->where('status', 'leave')->first()?->cnt    ?? 0),
                ])->values()
            );

        // Class names (all in school, ordered)
        $classNameMap = CourseClass::where('school_id', $schoolId)
            ->orderBy('numeric_value')->orderBy('name')
            ->pluck('name', 'id');

        // Per-class enrollment for unmarked + % in breakdown
        $classEnrollment = $academicYearId
            ? StudentAcademicHistory::where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->when($classId,   fn($q) => $q->where('class_id',   $classId))
                ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
                ->where('status', 'current')
                ->selectRaw('class_id, COUNT(*) as enrolled')
                ->groupBy('class_id')
                ->pluck('enrolled', 'class_id')
                ->toArray()
            : [];

        $report = [];
        foreach ($records as $date => $rows) {
            $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'half_day' => 0, 'leave' => 0];
            foreach ($rows as $row) {
                if (isset($counts[$row->status])) {
                    $counts[$row->status] = (int) $row->cnt;
                }
            }
            $marked           = array_sum($counts);
            $effectivePresent = $counts['present'] + ($counts['late'] * 0.5) + ($counts['half_day'] * 0.5);

            // Build class-wise breakdown for this date
            $breakdown = ($classBreakdownRaw->get($date) ?? collect())
                ->map(fn($c) => [
                    'class_name' => $classNameMap[$c['class_id']] ?? ('Class ' . $c['class_id']),
                    'present'    => $c['present'],
                    'absent'     => $c['absent'],
                    'late'       => $c['late'],
                    'half_day'   => $c['half_day'],
                    'leave'      => $c['leave'],
                    'enrolled'   => (int) ($classEnrollment[$c['class_id']] ?? 0),
                    'unmarked'   => max(0, ($classEnrollment[$c['class_id']] ?? 0) - ($c['present'] + $c['absent'] + $c['late'] + $c['half_day'] + $c['leave'])),
                    'pct'        => ($classEnrollment[$c['class_id']] ?? 0) > 0
                        ? round(($c['present'] + $c['late'] * 0.5 + $c['half_day'] * 0.5) / $classEnrollment[$c['class_id']] * 100, 1)
                        : null,
                ])
                ->sortBy('class_name')
                ->values()
                ->all();

            $report[] = [
                'date'      => $date,
                'day'       => \Carbon\Carbon::parse($date)->format('D'),
                'present'   => $counts['present'],
                'absent'    => $counts['absent'],
                'late'      => $counts['late'],
                'half_day'  => $counts['half_day'],
                'leave'     => $counts['leave'],
                'marked'    => $marked,
                'unmarked'  => max(0, $enrolledCount - $marked),
                'total'     => $enrolledCount,
                'pct'       => $enrolledCount > 0 ? round($effectivePresent / $enrolledCount * 100, 1) : null,
                'breakdown' => $breakdown,
            ];
        }

        return Inertia::render('School/Attendance/DateWise', [
            'classes'           => $classes,
            'sections'          => $sections,
            'report'            => $report,
            'enrolledCount'     => $enrolledCount,
            'selectedClassId'   => $classId ? (int) $classId : null,
            'selectedSectionId' => $sectionId ? (int) $sectionId : null,
            'from'              => $from,
            'to'                => $to,
        ]);
    }

    /**
     * Rapid Scanner view
     */
    public function scanner()
    {
        $user = auth()->user();
        abort_if(!$user->can('create_attendance') && !$user->isSchoolManagement(), 403, 'You do not have permission to mark attendance.');

        return Inertia::render('School/Attendance/QRScanner');
    }

    /**
     * API for Rapid QR Scanner to mark present
     */
    public function rapidScan(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->can('create_attendance') && !$user->isSchoolManagement(), 403, 'You do not have permission to mark attendance.');

        $request->validate(['uuid' => 'required|string']);

        $student = \App\Models\Student::with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->where('uuid', $request->uuid)
            ->first();

        if (!$student) {
            return response()->json(['error' => 'Student not found or invalid QR.'], 404);
        }

        $academicYearId = app('current_academic_year_id');
        $schoolId = app('current_school_id');
        $history = $student->currentAcademicHistory;

        if (!$history) {
            return response()->json(['error' => "{$student->name} has no active academic record."], 400);
        }

        \App\Models\Attendance::updateOrCreate(
            [
                'school_id'  => $schoolId,
                'student_id' => $student->id,
                'date'       => now()->toDateString(),
            ],
            [
                'academic_year_id' => $academicYearId,
                'class_id'         => $history->class_id,
                'section_id'       => $history->section_id,
                'status'           => 'present',
                'marked_by'        => auth()->id(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Attendance marked Present.',
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'class_name' => $history->courseClass->name ?? 'N/A',
                'section_name' => $history->section->name ?? '',
                'photo_url' => $student->photo_url,
            ],
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);
    }
}
