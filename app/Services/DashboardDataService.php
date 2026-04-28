<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\CourseClass;
use App\Models\EditRequest;
use App\Models\ExamScheduleSubject;
use App\Models\FeePayment;
use App\Models\Holiday;
use App\Models\HostelFeePayment;
use App\Models\HostelRoom;
use App\Models\HostelStudent;
use App\Models\Leave;
use App\Models\Section;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StationaryFeePayment;
use App\Models\Student;
use App\Models\TransportFeePayment;
use App\Models\TransportRoute;
use App\Models\VisitorLog;
use App\Models\User;

/**
 * Aggregates all data the school admin dashboard renders.
 * Returns a flat shape consumed directly by Pages/School/Dashboard.vue.
 */
class DashboardDataService
{
    public function __construct(private FeeService $feeService) {}

    public function adminDashboard(int $schoolId, ?int $academicYearId, User $user): array
    {
        $today        = Carbon::today();
        $yesterday    = $today->copy()->subDay();
        $thisMonth    = $today->copy()->startOfMonth();
        $thisMonthEnd = $today->copy()->endOfMonth();
        $lastMonth    = $today->copy()->subMonth()->startOfMonth();

        return [
            'kpi'                  => $this->kpis($schoolId, $academicYearId, $today, $yesterday, $thisMonth, $thisMonthEnd, $lastMonth),
            'sparklines'           => $this->sparklines($schoolId, $today),
            'fee_trend'            => $this->stackedFeeTrend($schoolId),
            'admission_trend'      => $this->admissionTrend($schoolId),
            'attendance_donut'     => $this->attendanceDonut($schoolId, $today),
            'class_attendance'     => $this->classAttendance($schoolId, $today),
            'fee_mix_today'        => $this->feeMixToday($schoolId, $today),
            'recent_payments'      => $this->recentPayments($schoolId),
            'recent_admissions'    => $this->recentAdmissions($schoolId),
            'today_visitors'       => $this->todayVisitors($schoolId, $today),
            'pending_fee_students' => $this->pendingFeeStudents($schoolId, $academicYearId),
            'low_attendance'       => $this->lowAttendance($schoolId, $academicYearId),
            'birthdays_today'      => $this->birthdaysToday($schoolId),
            'absent_staff'         => $this->absentStaff($schoolId, $today),
            'upcoming_exams'       => $this->upcomingExams($schoolId, $today),
            'upcoming_holidays'    => $this->upcomingHolidays($schoolId, $today),
            'calendar_exams'       => $this->calendarExams($schoolId, $today),
            'announcements'        => $this->announcements($schoolId),
            'next_exam'            => $this->nextExam($schoolId, $today),
            'pending_edit_count'   => EditRequest::where('school_id', $schoolId)->where('status', 'pending')->count(),
            'admin_name'           => $user->name,
            'generated_at'         => now()->toIso8601String(),
        ];
    }

    // ───────────────────────────────────────────────────────────────
    // KPIs with delta vs previous period
    // ───────────────────────────────────────────────────────────────
    private function kpis(int $schoolId, ?int $academicYearId, Carbon $today, Carbon $yesterday, Carbon $thisMonth, Carbon $thisMonthEnd, Carbon $lastMonth): array
    {
        $totalStudents = Student::where('school_id', $schoolId)
            ->where('status', 'active')
            ->when($academicYearId, fn($q) => $q->whereHas('academicHistories',
                fn($h) => $h->where('academic_year_id', $academicYearId)))
            ->count();

        $newStudentsThisMonth = Student::where('school_id', $schoolId)
            ->whereBetween('admission_date', [$thisMonth->toDateString(), $thisMonthEnd->toDateString()])
            ->when($academicYearId, fn($q) => $q->whereHas('academicHistories',
                fn($h) => $h->where('academic_year_id', $academicYearId)))
            ->count();

        $newStudentsLastMonthSameSpan = Student::where('school_id', $schoolId)
            ->whereBetween('admission_date', [
                $lastMonth->toDateString(),
                $lastMonth->copy()->setDay(min($today->day, $lastMonth->daysInMonth))->toDateString(),
            ])
            ->when($academicYearId, fn($q) => $q->whereHas('academicHistories',
                fn($h) => $h->where('academic_year_id', $academicYearId)))
            ->count();

        // Fees today/yesterday — sum of all four streams
        $todayFee     = $this->feeSumOnDay($schoolId, $today);
        $yesterdayFee = $this->feeSumOnDay($schoolId, $yesterday);
        $monthFee     = $this->feeSumInRange($schoolId, $thisMonth, $thisMonthEnd);

        // Last month's collection at the same point in the month (for fair MoM compare)
        $lastMonthSameSpanEnd = $lastMonth->copy()->setDay(min($today->day, $lastMonth->daysInMonth));
        $lastMonthSameSpanFee = $this->feeSumInRange($schoolId, $lastMonth, $lastMonthSameSpanEnd);

        // Pending fees (structure-aware)
        $schoolPending      = $this->feeService->getSchoolPendingFees($schoolId, $academicYearId);
        $pendingFees        = $schoolPending['pending_fees'];
        $pendingFeeStudents = count($schoolPending['pending_fee_students']);

        // Today's student attendance
        $todayAttn = Attendance::where('school_id', $schoolId)
            ->where('date', $today->toDateString())
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')->pluck('count', 'status')->toArray();
        $presentToday = ($todayAttn['present'] ?? 0) + ($todayAttn['late'] ?? 0) + ($todayAttn['half_day'] ?? 0);
        $absentToday  = $todayAttn['absent'] ?? 0;
        $totalMarked  = array_sum($todayAttn);
        $unmarked     = max(0, $totalStudents - $totalMarked);
        $attendancePct = $totalMarked > 0 ? round($presentToday / $totalMarked * 100, 1) : 0;

        // Rolling 7-day attendance avg (excluding today, for "vs week" delta)
        $weekAvgPct = $this->weekAvgAttendancePct($schoolId, $today);

        // Staff
        $totalStaff = Staff::where('school_id', $schoolId)->where('status', 'active')->count();
        $staffOnLeave = Leave::where('school_id', $schoolId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $today->toDateString())
            ->where('end_date', '>=', $today->toDateString())
            ->count();
        $staffAttn = StaffAttendance::where('school_id', $schoolId)
            ->where('date', $today->toDateString())
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')->pluck('count', 'status')->toArray();
        $staffPresent = ($staffAttn['present'] ?? 0) + ($staffAttn['late'] ?? 0) + ($staffAttn['half_day'] ?? 0);
        $staffMarked  = array_sum($staffAttn);
        $staffUnmarked = max(0, $totalStaff - $staffMarked);

        return [
            // counts
            'total_students'      => $totalStudents,
            'total_staff'         => $totalStaff,
            'total_classes'       => CourseClass::where('school_id', $schoolId)->count(),
            'total_sections'      => Section::where('school_id', $schoolId)->forCurrentYear()->count(),
            'active_routes'       => TransportRoute::where('school_id', $schoolId)->where('status', 'active')->count(),
            'hostel_occupied'     => HostelStudent::where('school_id', $schoolId)->where('status', 'active')->count(),
            'hostel_capacity'     => (int) HostelRoom::whereHas('hostel', fn($q) => $q->where('school_id', $schoolId))->sum('capacity'),

            // money — with deltas
            'today_fee'                 => $todayFee,
            'today_fee_prev'            => $yesterdayFee,
            'today_fee_delta_pct'       => $this->deltaPct($todayFee, $yesterdayFee),
            'month_fee'                 => $monthFee,
            'month_fee_prev'            => $lastMonthSameSpanFee,
            'month_fee_delta_pct'       => $this->deltaPct($monthFee, $lastMonthSameSpanFee),
            'pending_fees'              => $pendingFees,
            'pending_fee_count'         => $pendingFeeStudents,

            // attendance — with deltas
            'present_today'         => $presentToday,
            'absent_today'          => $absentToday,
            'attendance_marked'     => $totalMarked,
            'attendance_unmarked'   => $unmarked,
            'attendance_pct'        => $attendancePct,
            'attendance_pct_week'   => $weekAvgPct,
            'attendance_delta_pp'   => round($attendancePct - $weekAvgPct, 1),

            // admissions — with delta
            'new_students_month'        => $newStudentsThisMonth,
            'new_students_prev'         => $newStudentsLastMonthSameSpan,
            'new_students_delta_pct'    => $this->deltaPct($newStudentsThisMonth, $newStudentsLastMonthSameSpan),

            // staff
            'staff_on_leave'        => $staffOnLeave,
            'staff_present_today'   => $staffPresent,
            'staff_unmarked_today'  => $staffUnmarked,
        ];
    }

    // 7-day rolling sparklines for the four hero KPIs
    private function sparklines(int $schoolId, Carbon $today): array
    {
        $days = collect(range(6, 0))->map(fn($i) => $today->copy()->subDays($i));

        $fee = $days->map(fn($d) => $this->feeSumOnDay($schoolId, $d))->all();

        $admissions = $days->map(fn($d) => Student::where('school_id', $schoolId)
            ->whereDate('admission_date', $d->toDateString())->count())->all();

        $attendance = $days->map(function ($d) use ($schoolId) {
            $row = Attendance::where('school_id', $schoolId)
                ->where('date', $d->toDateString())
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')->pluck('count', 'status')->toArray();
            $present = ($row['present'] ?? 0) + ($row['late'] ?? 0) + ($row['half_day'] ?? 0);
            $marked  = array_sum($row);
            return $marked > 0 ? round($present / $marked * 100, 1) : null;
        })->all();

        return [
            'fee'        => $fee,
            'admissions' => $admissions,
            'attendance' => $attendance,
            'days'       => $days->map(fn($d) => $d->format('D'))->all(),
        ];
    }

    // ───────────────────────────────────────────────────────────────
    // Charts
    // ───────────────────────────────────────────────────────────────
    private function stackedFeeTrend(int $schoolId): array
    {
        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $tuition = (float) FeePayment::where('school_id', $schoolId)
                ->whereYear('payment_date', $m->year)->whereMonth('payment_date', $m->month)
                ->where('status', 'paid')->sum('amount_paid');
            $transport = (float) TransportFeePayment::where('school_id', $schoolId)
                ->whereYear('payment_date', $m->year)->whereMonth('payment_date', $m->month)->sum('amount_paid');
            $hostel = (float) HostelFeePayment::where('school_id', $schoolId)
                ->whereYear('payment_date', $m->year)->whereMonth('payment_date', $m->month)->sum('amount_paid');
            $stationary = (float) StationaryFeePayment::where('school_id', $schoolId)
                ->whereYear('payment_date', $m->year)->whereMonth('payment_date', $m->month)->sum('amount_paid');
            $out[] = [
                'month'      => $m->format('M Y'),
                'short'      => $m->format('M'),
                'tuition'    => $tuition,
                'transport'  => $transport,
                'hostel'     => $hostel,
                'stationary' => $stationary,
                'total'      => $tuition + $transport + $hostel + $stationary,
            ];
        }
        return $out;
    }

    private function admissionTrend(int $schoolId): array
    {
        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $out[] = [
                'month' => $m->format('M Y'),
                'short' => $m->format('M'),
                'count' => Student::where('school_id', $schoolId)
                    ->whereYear('admission_date', $m->year)
                    ->whereMonth('admission_date', $m->month)->count(),
            ];
        }
        return $out;
    }

    private function attendanceDonut(int $schoolId, Carbon $today): array
    {
        $row = Attendance::where('school_id', $schoolId)
            ->where('date', $today->toDateString())
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')->pluck('count', 'status')->toArray();
        return [
            'present'  => $row['present']  ?? 0,
            'absent'   => $row['absent']   ?? 0,
            'late'     => $row['late']     ?? 0,
            'half_day' => $row['half_day'] ?? 0,
        ];
    }

    private function classAttendance(int $schoolId, Carbon $today): array
    {
        return Attendance::where('attendances.school_id', $schoolId)
            ->where('attendances.date', $today->toDateString())
            ->join('course_classes as cc', 'attendances.class_id', '=', 'cc.id')
            ->selectRaw('cc.id, cc.name as class_name,
                COUNT(*) as total,
                SUM(CASE WHEN attendances.status IN ("present","late","half_day") THEN 1 ELSE 0 END) as present_count')
            ->groupBy('cc.id', 'cc.name')
            ->orderBy('cc.name')
            ->limit(10)->get()
            ->map(fn($r) => [
                'class'   => $r->class_name,
                'present' => (int) $r->present_count,
                'total'   => (int) $r->total,
                'pct'     => $r->total > 0 ? round($r->present_count / $r->total * 100) : 0,
            ])->all();
    }

    private function feeMixToday(int $schoolId, Carbon $today): array
    {
        $tuition = (float) FeePayment::where('school_id', $schoolId)
            ->whereDate('payment_date', $today->toDateString())
            ->where('status', 'paid')->sum('amount_paid');
        $transport = (float) TransportFeePayment::where('school_id', $schoolId)
            ->whereDate('payment_date', $today->toDateString())->sum('amount_paid');
        $hostel = (float) HostelFeePayment::where('school_id', $schoolId)
            ->whereDate('payment_date', $today->toDateString())->sum('amount_paid');
        $stationary = (float) StationaryFeePayment::where('school_id', $schoolId)
            ->whereDate('payment_date', $today->toDateString())->sum('amount_paid');
        return [
            'tuition' => $tuition, 'transport' => $transport,
            'hostel' => $hostel, 'stationary' => $stationary,
        ];
    }

    // ───────────────────────────────────────────────────────────────
    // Lists & alerts
    // ───────────────────────────────────────────────────────────────
    private function recentPayments(int $schoolId): array
    {
        return FeePayment::where('school_id', $schoolId)
            ->with(['student', 'feeHead'])
            ->orderByDesc('created_at')->limit(6)->get()
            ->map(fn($p) => [
                'id'       => $p->id,
                'student'  => trim(($p->student?->first_name ?? '') . ' ' . ($p->student?->last_name ?? '')) ?: '—',
                'receipt'  => $p->receipt_no,
                'amount'   => (float) $p->amount_paid,
                'mode'     => $p->payment_mode,
                'fee_head' => $p->feeHead?->name ?? '—',
                'paid_at'  => $p->payment_date?->format('d M, h:i A') ?? '—',
            ])->all();
    }

    private function recentAdmissions(int $schoolId): array
    {
        return Student::where('school_id', $schoolId)
            ->with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->orderByDesc('created_at')->limit(6)->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'name'         => trim($s->first_name . ' ' . $s->last_name),
                'admission_no' => $s->admission_no,
                'class'        => $s->currentAcademicHistory?->courseClass?->name ?? '—',
                'section'      => $s->currentAcademicHistory?->section?->name ?? '—',
                'admitted_at'  => $s->admission_date ? Carbon::parse($s->admission_date)->format('d M Y') : '—',
                'photo_url'    => $s->photo_url,
            ])->all();
    }

    private function todayVisitors(int $schoolId, Carbon $today): array
    {
        return VisitorLog::where('school_id', $schoolId)
            ->whereDate('in_time', $today->toDateString())
            ->orderByDesc('in_time')->limit(6)->get()
            ->map(fn($v) => [
                'id'      => $v->id,
                'name'    => $v->name,
                'purpose' => $v->purpose,
                'in_time' => $v->in_time?->format('h:i A') ?? '—',
                'out'     => $v->out_time?->format('h:i A'),
            ])->all();
    }

    private function pendingFeeStudents(int $schoolId, ?int $academicYearId): array
    {
        $r = $this->feeService->getSchoolPendingFees($schoolId, $academicYearId);
        // The service returns the full list; show top 8 by balance for the dashboard.
        return collect($r['pending_fee_students'] ?? [])
            ->sortByDesc('balance')->take(8)->values()->all();
    }

    private function lowAttendance(int $schoolId, ?int $academicYearId): array
    {
        if (!$academicYearId) return [];
        return Attendance::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->selectRaw('student_id,
                COUNT(*) as total,
                SUM(CASE WHEN status IN ("present","late","half_day") THEN 1 ELSE 0 END) as present_count')
            ->groupBy('student_id')
            ->havingRaw('total > 5 AND (present_count / total * 100) < 75')
            ->with('student')
            ->orderByRaw('present_count / total ASC')
            ->limit(8)->get()
            ->map(fn($a) => [
                'student' => trim(($a->student?->first_name ?? '') . ' ' . ($a->student?->last_name ?? '')) ?: '—',
                'percentage' => $a->total > 0 ? round($a->present_count / $a->total * 100, 1) : 0,
            ])->all();
    }

    private function birthdaysToday(int $schoolId): array
    {
        return Student::where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereMonth('dob', now()->month)
            ->whereDay('dob', now()->day)
            ->limit(10)->get()
            ->map(fn($s) => [
                'name'  => trim($s->first_name . ' ' . $s->last_name),
                'photo' => $s->photo_url,
            ])->all();
    }

    private function absentStaff(int $schoolId, Carbon $today): array
    {
        $leaveUserIds = Leave::where('school_id', $schoolId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $today->toDateString())
            ->where('end_date', '>=', $today->toDateString())
            ->pluck('user_id');
        $markedAbsentIds = StaffAttendance::where('school_id', $schoolId)
            ->where('date', $today->toDateString())
            ->whereIn('status', ['absent', 'leave'])
            ->pluck('staff_id');

        return Staff::where('school_id', $schoolId)
            ->where(function ($q) use ($leaveUserIds, $markedAbsentIds) {
                $q->whereIn('user_id', $leaveUserIds)
                  ->orWhereIn('id', $markedAbsentIds);
            })
            ->with(['user', 'designation'])
            ->limit(8)->get()
            ->map(fn($s) => [
                'name'        => $s->user?->name ?? '—',
                'designation' => $s->designation?->name ?? '—',
                'photo'       => $s->photo_url ?? null,
            ])->all();
    }

    private function upcomingExams(int $schoolId, Carbon $today): array
    {
        return ExamScheduleSubject::whereHas('examSchedule', fn($q) => $q->where('school_id', $schoolId))
            ->whereNotNull('exam_date')
            ->where('exam_date', '>=', $today->toDateString())
            ->with(['examSchedule.examType', 'subject'])
            ->orderBy('exam_date')->limit(5)->get()
            ->map(fn($es) => [
                'exam'    => $es->examSchedule?->examType?->name ?? '—',
                'subject' => $es->subject?->name ?? '—',
                'date'    => Carbon::parse($es->exam_date)->format('d M Y'),
            ])->all();
    }

    private function upcomingHolidays(int $schoolId, Carbon $today): array
    {
        return Holiday::where('school_id', $schoolId)
            ->where('date', '>=', $today->toDateString())
            ->where('date', '<=', $today->copy()->addDays(60)->toDateString())
            ->orderBy('date')->get()
            ->map(fn($h) => [
                'id'    => $h->id,
                'title' => $h->title,
                'date'  => $h->date->format('Y-m-d'),
                'end'   => $h->end_date?->format('Y-m-d'),
                'type'  => $h->type ?? 'holiday',
            ])->all();
    }

    private function calendarExams(int $schoolId, Carbon $today): array
    {
        return ExamScheduleSubject::whereHas('examSchedule', fn($q) => $q->where('school_id', $schoolId))
            ->whereNotNull('exam_date')
            ->where('exam_date', '>=', $today->toDateString())
            ->where('exam_date', '<=', $today->copy()->addDays(60)->toDateString())
            ->with(['examSchedule.examType', 'subject'])
            ->orderBy('exam_date')->get()
            ->map(fn($es) => [
                'title' => ($es->examSchedule?->examType?->name ?? 'Exam') . ' – ' . ($es->subject?->name ?? ''),
                'date'  => $es->exam_date,
                'type'  => 'exam',
            ])->all();
    }

    private function announcements(int $schoolId): array
    {
        return Announcement::where('school_id', $schoolId)
            ->with('sender')
            ->orderByDesc('created_at')->limit(5)->get()
            ->map(fn($a) => [
                'id'       => $a->id,
                'title'    => $a->title,
                'sender'   => $a->sender?->name ?? 'System',
                'audience' => $a->audience_type,
                'sent_at'  => $a->created_at->diffForHumans(),
            ])->all();
    }

    private function nextExam(int $schoolId, Carbon $today): ?array
    {
        $row = ExamScheduleSubject::whereHas('examSchedule', fn($q) => $q->where('school_id', $schoolId))
            ->whereNotNull('exam_date')
            ->where('exam_date', '>=', $today->toDateString())
            ->with(['examSchedule.examType'])
            ->orderBy('exam_date')->first();
        if (!$row) return null;
        $daysLeft = $today->copy()->startOfDay()->diffInDays(Carbon::parse($row->exam_date)->startOfDay());
        return [
            'title'     => $row->examSchedule?->examType?->name ?? 'Exam',
            'date'      => Carbon::parse($row->exam_date)->format('d M Y'),
            'days_left' => (int) $daysLeft,
        ];
    }

    // ───────────────────────────────────────────────────────────────
    // Helpers
    // ───────────────────────────────────────────────────────────────
    private function feeSumOnDay(int $schoolId, Carbon $day): float
    {
        $d = $day->toDateString();
        return (float) FeePayment::where('school_id', $schoolId)->whereDate('payment_date', $d)->where('status', 'paid')->sum('amount_paid')
            + (float) TransportFeePayment::where('school_id', $schoolId)->whereDate('payment_date', $d)->sum('amount_paid')
            + (float) HostelFeePayment::where('school_id', $schoolId)->whereDate('payment_date', $d)->sum('amount_paid')
            + (float) StationaryFeePayment::where('school_id', $schoolId)->whereDate('payment_date', $d)->sum('amount_paid');
    }

    private function feeSumInRange(int $schoolId, Carbon $start, Carbon $end): float
    {
        $s = $start->toDateString();
        $e = $end->toDateString();
        return (float) FeePayment::where('school_id', $schoolId)->whereBetween('payment_date', [$s, $e])->where('status', 'paid')->sum('amount_paid')
            + (float) TransportFeePayment::where('school_id', $schoolId)->whereBetween('payment_date', [$s, $e])->sum('amount_paid')
            + (float) HostelFeePayment::where('school_id', $schoolId)->whereBetween('payment_date', [$s, $e])->sum('amount_paid')
            + (float) StationaryFeePayment::where('school_id', $schoolId)->whereBetween('payment_date', [$s, $e])->sum('amount_paid');
    }

    private function deltaPct(float $current, float $previous): ?float
    {
        if ($previous == 0.0) return $current > 0 ? null : 0.0; // null = "n/a", chart will hide
        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function weekAvgAttendancePct(int $schoolId, Carbon $today): float
    {
        $start = $today->copy()->subDays(7)->toDateString();
        $end   = $today->copy()->subDay()->toDateString();
        $row = Attendance::where('school_id', $schoolId)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('
                SUM(CASE WHEN status IN ("present","late","half_day") THEN 1 ELSE 0 END) as p,
                COUNT(*) as t
            ')->first();
        if (!$row || !$row->t) return 0.0;
        return round($row->p / $row->t * 100, 1);
    }
}
