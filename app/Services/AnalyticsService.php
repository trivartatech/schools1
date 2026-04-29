<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Leave;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Daily attendance rate trend over an arbitrary date range.
     */
    public function attendanceTrend(int $schoolId, ?int $academicYearId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $to   = $to   ?: now();
        $from = $from ?: $to->copy()->subDays(29);

        $days = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days->push($d->toDateString());
        }

        $totalEnrolled = Student::where('school_id', $schoolId)
            ->where('status', 'active')
            ->enrolledInYear($academicYearId)
            ->count();

        $records = Attendance::where('school_id', $schoolId)
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->whereBetween('date', [$days->first(), $days->last()])
            ->select('date', 'status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('date', 'status')
            ->get()
            ->groupBy('date');

        $denom = max(1, $totalEnrolled);

        return $days->map(function ($date) use ($records, $denom) {
            $dayRecords = $records->get($date, collect());
            $total   = $dayRecords->sum('cnt');
            $present = $dayRecords->whereIn('status', ['present', 'late', 'half_day'])->sum('cnt');
            return [
                'date'  => $date,
                'rate'  => $total > 0 ? round($present / $denom * 100, 1) : null,
                'total' => $total,
            ];
        })->values()->all();
    }

    /**
     * Last 12 months collected fees vs. monthly target derived from FeeStructure.
     */
    public function feeCollectionTrend(int $schoolId, ?int $academicYearId): array
    {
        $months = collect(range(11, 0))->map(fn($i) => now()->startOfMonth()->subMonths($i));

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $monthFmt = $isSqlite ? "strftime('%Y-%m', payment_date)" : "DATE_FORMAT(payment_date, '%Y-%m')";

        $collections = FeePayment::where('school_id', $schoolId)
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->where('amount_paid', '>', 0)
            ->whereBetween('payment_date', [
                $months->first()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ])
            ->select(
                DB::raw("$monthFmt as month"),
                DB::raw('SUM(amount_paid) as collected'),
                DB::raw('COUNT(*) as payments')
            )
            ->groupByRaw($monthFmt)
            ->pluck(null, 'month');

        $annualTarget  = FeeStructure::where('school_id', $schoolId)
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->sum('amount');
        $monthlyTarget = $annualTarget > 0 ? round($annualTarget / 12, 2) : 0;

        return $months->map(function ($m) use ($collections, $monthlyTarget) {
            $key  = $m->format('Y-m');
            $data = $collections->get($key);
            return [
                'month'     => $m->format('M Y'),
                'collected' => $data ? (float) $data->collected : 0,
                'target'    => $monthlyTarget,
                'payments'  => $data ? (int) $data->payments : 0,
            ];
        })->values()->all();
    }

    public function enrollmentByClass(int $schoolId, ?int $academicYearId): array
    {
        return Student::where('students.school_id', $schoolId)
            ->where('students.status', 'active')
            ->join('student_academic_histories as h', function ($j) use ($academicYearId) {
                $j->on('h.student_id', '=', 'students.id')
                  ->when($academicYearId, fn($q) => $q->where('h.academic_year_id', $academicYearId));
            })
            ->join('course_classes as c', 'c.id', '=', 'h.class_id')
            ->select('c.name as class', DB::raw('COUNT(DISTINCT students.id) as count'))
            ->groupBy('c.id', 'c.name', 'c.sort_order')
            ->orderBy('c.sort_order')
            ->get()
            ->toArray();
    }

    public function examPerformance(int $schoolId, ?int $academicYearId): array
    {
        $rows = DB::table('exam_marks as em')
            ->join('exam_schedule_subjects as ess',  'ess.id',  '=', 'em.exam_schedule_subject_id')
            ->join('exam_schedules as es',           'es.id',   '=', 'ess.exam_schedule_id')
            ->join('student_academic_histories as h','h.student_id', '=', 'em.student_id')
            ->join('course_classes as c',            'c.id',    '=', 'h.class_id')
            ->leftJoin('exam_schedule_subject_marks as essm', function ($j) {
                $j->on('essm.exam_schedule_subject_id', '=', 'em.exam_schedule_subject_id')
                  ->on('essm.exam_assessment_item_id',  '=', 'em.exam_assessment_item_id');
            })
            ->where('em.school_id', $schoolId)
            ->where('em.is_absent', false)
            ->when($academicYearId, fn($q) => $q->where('em.academic_year_id', $academicYearId)
                ->where('h.academic_year_id', $academicYearId))
            ->select(
                'c.name as class',
                'c.sort_order',
                DB::raw('AVG(em.marks_obtained / NULLIF(essm.max_marks, 0) * 100) as avg_pct'),
                DB::raw('COUNT(DISTINCT em.student_id) as students'),
                DB::raw('SUM(CASE WHEN em.marks_obtained >= essm.passing_marks THEN 1 ELSE 0 END) as passed'),
                DB::raw('COUNT(*) as total_marks')
            )
            ->groupBy('c.id', 'c.name', 'c.sort_order')
            ->orderBy('c.sort_order')
            ->get();

        return $rows->map(fn($r) => [
            'class'     => $r->class,
            'avg_pct'   => $r->avg_pct ? round((float) $r->avg_pct, 1) : null,
            'students'  => (int) $r->students,
            'pass_rate' => $r->total_marks > 0 ? round($r->passed / $r->total_marks * 100, 1) : null,
        ])->values()->all();
    }

    public function staffLeaveHeatmap(int $schoolId): array
    {
        $isSqlite  = DB::connection()->getDriverName() === 'sqlite';
        $monthExpr = $isSqlite
            ? "CAST(strftime('%m', start_date) AS INTEGER)"
            : "MONTH(start_date)";
        $daysExpr  = $isSqlite
            ? "SUM(CAST(julianday(end_date) - julianday(start_date) + 1 AS INTEGER))"
            : "SUM(DATEDIFF(end_date, start_date) + 1)";

        $rows = Leave::where('school_id', $schoolId)
            ->where('status', 'approved')
            ->whereYear('start_date', now()->year)
            ->select(
                DB::raw("$monthExpr as month"),
                DB::raw('COUNT(*) as count'),
                DB::raw("$daysExpr as days")
            )
            ->groupByRaw($monthExpr)
            ->orderByRaw($monthExpr)
            ->get();

        $months = collect(range(1, 12))->map(fn($m) => [
            'month' => Carbon::createFromDate(null, $m, 1)->format('M'),
            'count' => 0,
            'days'  => 0,
        ])->toArray();

        foreach ($rows as $r) {
            $months[$r->month - 1]['count'] = (int) $r->count;
            $months[$r->month - 1]['days']  = (int) $r->days;
        }

        return $months;
    }

    public function summaryStats(int $schoolId, ?int $academicYearId): array
    {
        $today = now()->toDateString();

        $totalStudents = Student::where('school_id', $schoolId)
            ->where('status', 'active')
            ->enrolledInYear($academicYearId)
            ->count();

        $todayAttendance = Attendance::where('school_id', $schoolId)
            ->where('date', $today)
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $presentToday = (int)($todayAttendance->get('present', 0))
            + (int)($todayAttendance->get('late', 0))
            + (int)($todayAttendance->get('half_day', 0));
        $markedToday  = (int) $todayAttendance->sum();

        $thisMonthFee = FeePayment::where('school_id', $schoolId)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->where('amount_paid', '>', 0)
            ->sum('amount_paid');

        $pendingLeaves = Leave::where('school_id', $schoolId)
            ->where('status', 'pending')
            ->count();

        return [
            'total_students' => $totalStudents,
            'present_today'  => $presentToday,
            'marked_today'   => $markedToday,
            'unmarked_today' => max(0, $totalStudents - $markedToday),
            'attendance_pct' => $markedToday > 0 ? round($presentToday / max(1, $totalStudents) * 100, 1) : null,
            'this_month_fee' => (float) $thisMonthFee,
            'pending_leaves' => $pendingLeaves,
        ];
    }

    public function topFeeDefaulters(int $schoolId, ?int $academicYearId, int $limit = 10): array
    {
        $pending = app(\App\Services\FeeService::class)->getSchoolPendingFees($schoolId, $academicYearId);
        return collect($pending['pending_fee_students'] ?? [])
            ->sortByDesc('balance')
            ->take($limit)
            ->map(fn($r) => [
                'student' => $r['student'] ?? null,
                'balance' => round((float) ($r['balance'] ?? 0), 2),
            ])
            ->values()
            ->all();
    }
}
