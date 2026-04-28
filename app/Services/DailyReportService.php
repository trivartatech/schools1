<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\CourseClass;
use App\Models\DailyReportSetting;
use App\Models\Expense;
use App\Models\FeePayment;
use App\Models\Holiday;
use App\Models\HostelFeePayment;
use App\Models\Section;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StationaryFeePayment;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\TransportFeePayment;
use App\Models\VisitorLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the day's activity into a single payload that drives the
 * Daily Master Report page, the PDF, and the WhatsApp/SMS broadcast.
 *
 * One source of truth — page and message stay in sync.
 */
class DailyReportService
{
    public function __construct(private FeeService $feeService) {}

    /**
     * Build the report payload for a single date.
     */
    public function forDate(int $schoolId, Carbon $date, ?int $academicYearId = null): array
    {
        $date = $date->copy()->startOfDay();
        $settings = DailyReportSetting::forSchool($schoolId);

        return [
            'meta' => [
                'school_id'        => $schoolId,
                'academic_year_id' => $academicYearId,
                'mode'             => 'daily',
                'date'             => $date->toDateString(),
                'date_label'       => $date->format('l, d M Y'),
                'generated_at'     => now()->toIso8601String(),
                'sections_enabled' => $settings->sections_enabled ?: DailyReportSetting::ALL_SECTIONS,
            ],
            'kpi'        => $this->kpis($schoolId, $date, $academicYearId),
            'alerts'     => app(DailyReportAnomalies::class)->detect($schoolId, $date, $academicYearId, $settings),
            'highlights' => $this->highlights($schoolId, $date, $academicYearId),
            'attendance' => [
                'donut'                  => $this->attendanceDonut($schoolId, $date, $academicYearId),
                'staff'                  => $this->staffAttendance($schoolId, $date),
                'class_section_table'    => $this->classSectionAttendance($schoolId, $date, $academicYearId),
                'unmarked_classes'       => $this->unmarkedClasses($schoolId, $date, $academicYearId),
            ],
            'fees' => [
                'total'           => $this->feeSumOnDay($schoolId, $date, $academicYearId),
                'streams'         => $this->feeStreams($schoolId, $date, $academicYearId),
                'by_payment_mode' => $this->feeByPaymentMode($schoolId, $date, $academicYearId),
                'by_class'        => $this->feeByClass($schoolId, $date, $academicYearId),
                'top_collectors'  => $this->topCollectors($schoolId, $date, $academicYearId),
                'pending_dues'    => $this->pendingDues($schoolId, $academicYearId),
            ],
            'expenses' => [
                'total'           => $this->expenseSumOnDay($schoolId, $date, $academicYearId),
                'by_category'     => $this->expenseByCategory($schoolId, $date, $academicYearId),
                'by_payment_mode' => $this->expenseByPaymentMode($schoolId, $date, $academicYearId),
                'top_vouchers'    => $this->topExpenseVouchers($schoolId, $date, $academicYearId),
            ],
            'cash'      => $this->cashFlow($schoolId, $date, $academicYearId),
            'admissions'=> $this->admissionsToday($schoolId, $date, $academicYearId),
            'events'    => $this->dayEvents($schoolId, $date, $academicYearId),
            'outlook'   => $this->tomorrowOutlook($schoolId, $date, $academicYearId),
        ];
    }

    /**
     * Weekly digest variant — Monday to Sunday rollup. Same shape as forDate
     * but date-range aggregations.
     */
    public function forWeek(int $schoolId, Carbon $weekStart, Carbon $weekEnd, ?int $academicYearId = null): array
    {
        $weekStart = $weekStart->copy()->startOfDay();
        $weekEnd   = $weekEnd->copy()->endOfDay();
        $settings  = DailyReportSetting::forSchool($schoolId);

        return [
            'meta' => [
                'school_id'        => $schoolId,
                'academic_year_id' => $academicYearId,
                'mode'             => 'weekly',
                'date'             => $weekEnd->toDateString(),
                'date_label'       => 'Week ' . $weekStart->format('d M') . ' – ' . $weekEnd->format('d M Y'),
                'week_start'       => $weekStart->toDateString(),
                'week_end'         => $weekEnd->toDateString(),
                'generated_at'     => now()->toIso8601String(),
                'sections_enabled' => $settings->sections_enabled ?: DailyReportSetting::ALL_SECTIONS,
            ],
            'kpi'        => $this->weeklyKpis($schoolId, $weekStart, $weekEnd, $academicYearId),
            'alerts'     => [], // weekly digest skips per-day flags
            'highlights' => $this->highlights($schoolId, $weekEnd, $academicYearId),
            'attendance' => [
                'week_avg_pct'     => $this->weekAvgAttendancePct($schoolId, $weekStart, $weekEnd, $academicYearId),
                'daily_breakdown'  => $this->weekDailyAttendance($schoolId, $weekStart, $weekEnd, $academicYearId),
            ],
            'fees' => [
                'total'   => $this->feeSumInRange($schoolId, $weekStart, $weekEnd, $academicYearId),
                'streams' => $this->feeStreamsInRange($schoolId, $weekStart, $weekEnd, $academicYearId),
            ],
            'expenses' => [
                'total'       => $this->expenseSumInRange($schoolId, $weekStart, $weekEnd, $academicYearId),
                'by_category' => $this->expenseByCategoryInRange($schoolId, $weekStart, $weekEnd, $academicYearId),
            ],
            'cash'       => $this->cashFlowInRange($schoolId, $weekStart, $weekEnd, $academicYearId),
            'admissions' => $this->admissionsInRange($schoolId, $weekStart, $weekEnd, $academicYearId),
            'outlook'    => $this->tomorrowOutlook($schoolId, $weekEnd, $academicYearId),
        ];
    }

    // ───────────────────────────────────────────────────────────────
    // KPIs with comparison deltas
    // ───────────────────────────────────────────────────────────────
    private function kpis(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        $yesterday   = $date->copy()->subDay();
        $sameDowLW   = $date->copy()->subWeek();
        $monthStart  = $date->copy()->startOfMonth();

        // Attendance %
        $attToday  = $this->attendancePct($schoolId, $date, $academicYearId);
        $attYest   = $this->attendancePct($schoolId, $yesterday, $academicYearId);
        $attDow    = $this->attendancePct($schoolId, $sameDowLW, $academicYearId);
        $attMtdAvg = $this->avgAttendancePctRange($schoolId, $monthStart, $date->copy()->subDay(), $academicYearId);

        // Fees collected
        $feeToday = $this->feeSumOnDay($schoolId, $date, $academicYearId);
        $feeYest  = $this->feeSumOnDay($schoolId, $yesterday, $academicYearId);
        $feeDow   = $this->feeSumOnDay($schoolId, $sameDowLW, $academicYearId);
        $feeMtdAvg = $this->avgFeePerDayRange($schoolId, $monthStart, $date->copy()->subDay(), $academicYearId);

        // Expenses
        $expToday = $this->expenseSumOnDay($schoolId, $date, $academicYearId);
        $expYest  = $this->expenseSumOnDay($schoolId, $yesterday, $academicYearId);
        $expDow   = $this->expenseSumOnDay($schoolId, $sameDowLW, $academicYearId);
        $expMtdAvg = $this->avgExpensePerDayRange($schoolId, $monthStart, $date->copy()->subDay(), $academicYearId);

        // New admissions
        $admToday = $this->admissionsCountOnDay($schoolId, $date, $academicYearId);
        $admYest  = $this->admissionsCountOnDay($schoolId, $yesterday, $academicYearId);
        $admDow   = $this->admissionsCountOnDay($schoolId, $sameDowLW, $academicYearId);
        $admMtdAvg = $this->avgAdmissionsPerDayRange($schoolId, $monthStart, $date->copy()->subDay(), $academicYearId);

        // Visitors
        $visToday = $this->visitorsCountOnDay($schoolId, $date);
        $visYest  = $this->visitorsCountOnDay($schoolId, $yesterday);
        $visDow   = $this->visitorsCountOnDay($schoolId, $sameDowLW);

        return [
            'attendance_pct' => $this->compareKpi($attToday, $attYest, $attDow, $attMtdAvg, 'pp'),
            'fee_total'      => $this->compareKpi($feeToday, $feeYest, $feeDow, $feeMtdAvg),
            'expense_total'  => $this->compareKpi($expToday, $expYest, $expDow, $expMtdAvg),
            'net_position'   => [
                'value'           => $feeToday - $expToday,
                'is_positive'     => ($feeToday - $expToday) >= 0,
            ],
            'new_admissions' => $this->compareKpi($admToday, $admYest, $admDow, $admMtdAvg),
            'visitors'       => $this->compareKpi($visToday, $visYest, $visDow, 0.0),
        ];
    }

    private function weeklyKpis(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): array
    {
        $prevStart = $start->copy()->subWeek();
        $prevEnd   = $end->copy()->subWeek();

        return [
            'attendance_pct' => [
                'value'             => $this->avgAttendancePctRange($schoolId, $start, $end, $academicYearId),
                'vs_last_week'      => $this->avgAttendancePctRange($schoolId, $prevStart, $prevEnd, $academicYearId),
            ],
            'fee_total'      => [
                'value'             => $this->feeSumInRange($schoolId, $start, $end, $academicYearId),
                'vs_last_week'      => $this->feeSumInRange($schoolId, $prevStart, $prevEnd, $academicYearId),
            ],
            'expense_total'  => [
                'value'             => $this->expenseSumInRange($schoolId, $start, $end, $academicYearId),
                'vs_last_week'      => $this->expenseSumInRange($schoolId, $prevStart, $prevEnd, $academicYearId),
            ],
            'new_admissions' => [
                'value'             => $this->admissionsCountInRange($schoolId, $start, $end, $academicYearId),
                'vs_last_week'      => $this->admissionsCountInRange($schoolId, $prevStart, $prevEnd, $academicYearId),
            ],
        ];
    }

    /**
     * Standard KPI shape — value plus three deltas. Unit can be 'pct'
     * (percentage delta) or 'pp' (percentage points, used for already-pct
     * values like attendance %).
     */
    private function compareKpi(float $today, float $yesterday, float $sameDowLW, float $mtdAvg, string $unit = 'pct'): array
    {
        return [
            'value'                  => $today,
            'vs_yesterday'           => $yesterday,
            'vs_yesterday_delta'     => $unit === 'pp'
                ? round($today - $yesterday, 1)
                : $this->deltaPct($today, $yesterday),
            'vs_last_week_same_day'  => $sameDowLW,
            'vs_last_week_delta'     => $unit === 'pp'
                ? round($today - $sameDowLW, 1)
                : $this->deltaPct($today, $sameDowLW),
            'vs_mtd_avg'             => $mtdAvg,
            'vs_mtd_avg_delta'       => $unit === 'pp'
                ? round($today - $mtdAvg, 1)
                : $this->deltaPct($today, $mtdAvg),
            'unit'                   => $unit,
        ];
    }

    // ───────────────────────────────────────────────────────────────
    // Attendance
    // ───────────────────────────────────────────────────────────────
    private function attendanceDonut(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        $row = Attendance::where('school_id', $schoolId)
            ->where('date', $date->toDateString())
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')->pluck('count', 'status')->toArray();

        return [
            'present'  => (int) ($row['present']  ?? 0),
            'absent'   => (int) ($row['absent']   ?? 0),
            'late'     => (int) ($row['late']     ?? 0),
            'half_day' => (int) ($row['half_day'] ?? 0),
            'leave'    => (int) ($row['leave']    ?? 0),
            'holiday'  => (int) ($row['holiday']  ?? 0),
            'total_marked' => (int) array_sum($row),
        ];
    }

    private function staffAttendance(int $schoolId, Carbon $date): array
    {
        // Active staff with their current attendance row (if any) for the day
        $activeStaff = Staff::where('school_id', $schoolId)
            ->where('status', 'active')
            ->with(['user:id,name', 'designation:id,name'])
            ->get();

        $marked = StaffAttendance::where('school_id', $schoolId)
            ->where('date', $date->toDateString())
            ->get(['staff_id', 'status'])
            ->keyBy('staff_id');

        $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'half_day' => 0, 'leave' => 0, 'holiday' => 0];
        $absentList = [];
        $unmarkedList = [];

        foreach ($activeStaff as $staff) {
            $rec = $marked[$staff->id] ?? null;
            if (!$rec) {
                $unmarkedList[] = [
                    'name'        => $staff->user?->name ?? '—',
                    'designation' => $staff->designation?->name,
                ];
                continue;
            }

            $status = (string) $rec->status;
            if (isset($counts[$status])) $counts[$status]++;

            if (in_array($status, ['absent', 'leave'], true)) {
                $absentList[] = [
                    'name'        => $staff->user?->name ?? '—',
                    'designation' => $staff->designation?->name,
                    'status'      => $status,
                ];
            }
        }

        $totalStaff = $activeStaff->count();
        $markedTotal = $marked->count();

        return [
            'total'         => $totalStaff,
            'marked'        => $markedTotal,
            'present'       => $counts['present'] + $counts['late'] + $counts['half_day'],
            'absent'        => $counts['absent'],
            'leave'         => $counts['leave'],
            'half_day'      => $counts['half_day'],
            'unmarked'      => count($unmarkedList),
            'absent_list'   => $absentList,
            'unmarked_list' => $unmarkedList,
        ];
    }

    /**
     * Per class+section attendance — one row per class+section pair.
     * Uses StudentAcademicHistory to get the enrolled denominator (not just
     * marked) so percentages reflect true class size.
     */
    private function classSectionAttendance(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        if (!$academicYearId) return [];

        // Enrolled per class+section
        $enrolled = StudentAcademicHistory::where('student_academic_histories.academic_year_id', $academicYearId)
            ->whereHas('student', fn($q) => $q->where('school_id', $schoolId)->where('status', 'active'))
            ->selectRaw('class_id, section_id, COUNT(DISTINCT student_id) as enrolled')
            ->groupBy('class_id', 'section_id')
            ->get()
            ->keyBy(fn($r) => $r->class_id . '_' . ($r->section_id ?? 'null'));

        // Today's attendance counts per class+section
        $marked = Attendance::where('school_id', $schoolId)
            ->where('date', $date->toDateString())
            ->where('academic_year_id', $academicYearId)
            ->selectRaw('class_id, section_id, status, COUNT(*) as count')
            ->groupBy('class_id', 'section_id', 'status')
            ->get();

        $byKey = [];
        foreach ($marked as $row) {
            $key = $row->class_id . '_' . ($row->section_id ?? 'null');
            $byKey[$key][$row->status] = (int) $row->count;
        }

        // Class meta: name + numeric_value (for ordering)
        $classMeta = CourseClass::where('school_id', $schoolId)
            ->orderBy('numeric_value')->orderBy('id')
            ->get(['id', 'name', 'numeric_value'])
            ->keyBy('id');

        // Section meta: name + sort_order (for ordering)
        $sectionMeta = Section::where('school_id', $schoolId)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'sort_order'])
            ->keyBy('id');

        $rows = [];
        foreach ($enrolled as $key => $row) {
            $counts = $byKey[$key] ?? [];
            $present = (int) ($counts['present'] ?? 0);
            $late    = (int) ($counts['late'] ?? 0);
            $halfDay = (int) ($counts['half_day'] ?? 0);
            $absent  = (int) ($counts['absent'] ?? 0);
            $leave   = (int) ($counts['leave'] ?? 0);
            $holiday = (int) ($counts['holiday'] ?? 0);
            $totalMarked = $present + $late + $halfDay + $absent + $leave + $holiday;
            $effectivePresent = $present + ($late * 0.5) + ($halfDay * 0.5);
            $enrolledCount = (int) $row->enrolled;
            $unmarked = max(0, $enrolledCount - $totalMarked);

            $cls = $classMeta[$row->class_id] ?? null;
            $sec = $row->section_id ? ($sectionMeta[$row->section_id] ?? null) : null;

            $rows[] = [
                'class_id'         => $row->class_id,
                'class'            => $cls?->name ?? '—',
                'class_order'      => (int) ($cls?->numeric_value ?? PHP_INT_MAX),
                'section_id'       => $row->section_id,
                'section'          => $sec?->name,
                'section_order'    => (int) ($sec?->sort_order ?? PHP_INT_MAX),
                'enrolled'         => $enrolledCount,
                'marked'           => $totalMarked,
                'present'          => $present + $late + $halfDay,
                'absent'           => $absent,
                'leave'            => $leave,
                'unmarked'         => $unmarked,
                'pct'              => $totalMarked > 0 ? round($effectivePresent / $totalMarked * 100, 1) : 0,
            ];
        }

        // Sort by class.numeric_value, then section.sort_order — so the
        // table reflects the school's actual class progression (1, 2, 3 …)
        // instead of an alphabetic name sort.
        usort($rows, function ($a, $b) {
            if ($a['class_order'] !== $b['class_order']) {
                return $a['class_order'] <=> $b['class_order'];
            }
            return $a['section_order'] <=> $b['section_order'];
        });

        return $rows;
    }

    /**
     * Class+section pairs with zero attendance records for the day,
     * plus the assigned class/section incharge name.
     */
    private function unmarkedClasses(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        if (!$academicYearId) return [];

        // Sections that have at least one attendance record for the date
        $markedSectionIds = Attendance::where('school_id', $schoolId)
            ->where('date', $date->toDateString())
            ->where('academic_year_id', $academicYearId)
            ->pluck('section_id')
            ->filter()
            ->unique()
            ->toArray();

        // All active sections in this AY with enrolled students
        $allSections = StudentAcademicHistory::where('student_academic_histories.academic_year_id', $academicYearId)
            ->whereHas('student', fn($q) => $q->where('school_id', $schoolId)->where('status', 'active'))
            ->whereNotNull('section_id')
            ->select('class_id', 'section_id', DB::raw('COUNT(DISTINCT student_id) as enrolled'))
            ->groupBy('class_id', 'section_id')
            ->get();

        $unmarkedSections = $allSections->whereNotIn('section_id', $markedSectionIds);
        if ($unmarkedSections->isEmpty()) return [];

        $sectionIds = $unmarkedSections->pluck('section_id')->all();
        $classIds   = $unmarkedSections->pluck('class_id')->all();

        $sections = Section::with('inchargeStaff.user')
            ->whereIn('id', $sectionIds)->get()->keyBy('id');
        $classes  = CourseClass::with('inchargeStaff.user')
            ->whereIn('id', $classIds)->get()->keyBy('id');

        $rows = $unmarkedSections->map(function ($r) use ($sections, $classes) {
            $section = $sections[$r->section_id] ?? null;
            $class   = $classes[$r->class_id] ?? null;
            $teacherName = $section?->inchargeStaff?->user?->name
                ?? $class?->inchargeStaff?->user?->name
                ?? null;
            return [
                'class_id'      => $r->class_id,
                'class'         => $class?->name ?? '—',
                'class_order'   => (int) ($class?->numeric_value ?? PHP_INT_MAX),
                'section_id'    => $r->section_id,
                'section'       => $section?->name ?? '—',
                'section_order' => (int) ($section?->sort_order ?? PHP_INT_MAX),
                'enrolled'      => (int) $r->enrolled,
                'teacher'       => $teacherName,
            ];
        })->values()->all();

        // Same ordering as the main attendance table
        usort($rows, function ($a, $b) {
            if ($a['class_order'] !== $b['class_order']) {
                return $a['class_order'] <=> $b['class_order'];
            }
            return $a['section_order'] <=> $b['section_order'];
        });

        return $rows;
    }

    // ───────────────────────────────────────────────────────────────
    // Fees
    // ───────────────────────────────────────────────────────────────
    private function feeStreams(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        $d = $date->toDateString();
        $apply = fn($q) => $q->when($academicYearId, fn($x) => $x->where('academic_year_id', $academicYearId));

        $tuition = $apply(FeePayment::where('school_id', $schoolId)
            ->whereDate('payment_date', $d)
            ->where('amount_paid', '>', 0));
        $transport = $apply(TransportFeePayment::where('school_id', $schoolId)
            ->whereDate('payment_date', $d));
        $hostel = $apply(HostelFeePayment::where('school_id', $schoolId)
            ->whereDate('payment_date', $d));
        $stationary = $apply(StationaryFeePayment::where('school_id', $schoolId)
            ->whereDate('payment_date', $d));

        return [
            'tuition'    => ['amount' => (float) (clone $tuition)->sum('amount_paid'),    'count' => (clone $tuition)->count()],
            'transport'  => ['amount' => (float) (clone $transport)->sum('amount_paid'),  'count' => (clone $transport)->count()],
            'hostel'     => ['amount' => (float) (clone $hostel)->sum('amount_paid'),     'count' => (clone $hostel)->count()],
            'stationary' => ['amount' => (float) (clone $stationary)->sum('amount_paid'), 'count' => (clone $stationary)->count()],
        ];
    }

    private function feeStreamsInRange(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): array
    {
        $apply = fn($q) => $q->when($academicYearId, fn($x) => $x->where('academic_year_id', $academicYearId));
        $range = [$start->toDateString(), $end->toDateString()];

        $tuition = $apply(FeePayment::where('school_id', $schoolId)
            ->whereBetween('payment_date', $range)
            ->where('amount_paid', '>', 0));
        $transport = $apply(TransportFeePayment::where('school_id', $schoolId)
            ->whereBetween('payment_date', $range));
        $hostel = $apply(HostelFeePayment::where('school_id', $schoolId)
            ->whereBetween('payment_date', $range));
        $stationary = $apply(StationaryFeePayment::where('school_id', $schoolId)
            ->whereBetween('payment_date', $range));

        return [
            'tuition'    => ['amount' => (float) (clone $tuition)->sum('amount_paid'),    'count' => (clone $tuition)->count()],
            'transport'  => ['amount' => (float) (clone $transport)->sum('amount_paid'),  'count' => (clone $transport)->count()],
            'hostel'     => ['amount' => (float) (clone $hostel)->sum('amount_paid'),     'count' => (clone $hostel)->count()],
            'stationary' => ['amount' => (float) (clone $stationary)->sum('amount_paid'), 'count' => (clone $stationary)->count()],
        ];
    }

    private function feeByPaymentMode(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        $d = $date->toDateString();
        $apply = fn($q) => $q->when($academicYearId, fn($x) => $x->where('academic_year_id', $academicYearId));
        $modes = [];

        foreach ([FeePayment::class, TransportFeePayment::class, HostelFeePayment::class, StationaryFeePayment::class] as $model) {
            // Alias as `pm_raw` so Eloquent skips the PaymentMode enum cast
            // attached to `payment_mode` on the fee models — we want the raw
            // string here so it can be used as an array key.
            $rows = $apply($model::where('school_id', $schoolId)
                ->whereDate('payment_date', $d)
                ->where('amount_paid', '>', 0))
                ->selectRaw('payment_mode as pm_raw, SUM(amount_paid) as total, COUNT(*) as count')
                ->groupBy('payment_mode')
                ->get();
            foreach ($rows as $r) {
                $mode = $this->normalisePaymentMode($r->pm_raw);
                $modes[$mode]['amount'] = ($modes[$mode]['amount'] ?? 0) + (float) $r->total;
                $modes[$mode]['count']  = ($modes[$mode]['count']  ?? 0) + (int) $r->count;
            }
        }

        $out = [];
        foreach ($modes as $mode => $vals) {
            $out[] = ['mode' => $mode, 'amount' => $vals['amount'], 'count' => $vals['count']];
        }
        usort($out, fn($a, $b) => $b['amount'] <=> $a['amount']);
        return $out;
    }

    private function feeByClass(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        if (!$academicYearId) return [];
        $d = $date->toDateString();

        return FeePayment::where('fee_payments.school_id', $schoolId)
            ->whereDate('fee_payments.payment_date', $d)
            ->where('fee_payments.amount_paid', '>', 0)
            ->where('fee_payments.academic_year_id', $academicYearId)
            ->join('student_academic_histories as sah', function ($j) use ($academicYearId) {
                $j->on('sah.student_id', '=', 'fee_payments.student_id')
                  ->where('sah.academic_year_id', $academicYearId);
            })
            ->join('course_classes as cc', 'sah.class_id', '=', 'cc.id')
            ->selectRaw('cc.id, cc.name as class_name, SUM(fee_payments.amount_paid) as total, COUNT(*) as count')
            ->groupBy('cc.id', 'cc.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'class'  => $r->class_name,
                'amount' => (float) $r->total,
                'count'  => (int) $r->count,
            ])
            ->all();
    }

    private function topCollectors(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        $d = $date->toDateString();
        $apply = fn($q) => $q->when($academicYearId, fn($x) => $x->where('academic_year_id', $academicYearId));
        $byUser = [];

        foreach ([FeePayment::class, TransportFeePayment::class, HostelFeePayment::class, StationaryFeePayment::class] as $model) {
            $rows = $apply($model::where('school_id', $schoolId)
                ->whereDate('payment_date', $d)
                ->where('amount_paid', '>', 0)
                ->whereNotNull('collected_by'))
                ->selectRaw('collected_by, SUM(amount_paid) as total, COUNT(*) as count')
                ->groupBy('collected_by')
                ->get();
            foreach ($rows as $r) {
                $byUser[$r->collected_by]['amount'] = ($byUser[$r->collected_by]['amount'] ?? 0) + (float) $r->total;
                $byUser[$r->collected_by]['count']  = ($byUser[$r->collected_by]['count']  ?? 0) + (int) $r->count;
            }
        }

        if (empty($byUser)) return [];

        $users = \App\Models\User::whereIn('id', array_keys($byUser))->pluck('name', 'id');
        $out = [];
        foreach ($byUser as $userId => $vals) {
            $out[] = [
                'name'   => $users[$userId] ?? '—',
                'amount' => $vals['amount'],
                'count'  => $vals['count'],
            ];
        }
        usort($out, fn($a, $b) => $b['amount'] <=> $a['amount']);
        return array_slice($out, 0, 5);
    }

    private function pendingDues(int $schoolId, ?int $academicYearId): array
    {
        if (!$academicYearId) return ['amount' => 0, 'students' => 0];
        $r = $this->feeService->getSchoolPendingFees($schoolId, $academicYearId);
        return [
            'amount'   => (float) ($r['pending_fees'] ?? 0),
            'students' => count($r['pending_fee_students'] ?? []),
        ];
    }

    // ───────────────────────────────────────────────────────────────
    // Expenses
    // ───────────────────────────────────────────────────────────────
    private function expenseByCategory(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        return Expense::where('school_id', $schoolId)
            ->whereDate('expense_date', $date->toDateString())
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->with('category')
            ->get()
            ->groupBy(fn($e) => $e->category?->name ?? 'Uncategorised')
            ->map(fn($items) => [
                'amount' => (float) $items->sum('amount'),
                'count'  => $items->count(),
            ])
            ->sortByDesc('amount')
            ->take(8)
            ->map(fn($vals, $name) => ['category' => $name, 'amount' => $vals['amount'], 'count' => $vals['count']])
            ->values()
            ->all();
    }

    private function expenseByCategoryInRange(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): array
    {
        return Expense::where('school_id', $schoolId)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->with('category')
            ->get()
            ->groupBy(fn($e) => $e->category?->name ?? 'Uncategorised')
            ->map(fn($items) => [
                'amount' => (float) $items->sum('amount'),
                'count'  => $items->count(),
            ])
            ->sortByDesc('amount')
            ->take(10)
            ->map(fn($vals, $name) => ['category' => $name, 'amount' => $vals['amount'], 'count' => $vals['count']])
            ->values()
            ->all();
    }

    private function expenseByPaymentMode(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        return Expense::where('school_id', $schoolId)
            ->whereDate('expense_date', $date->toDateString())
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->selectRaw('payment_mode as pm_raw, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_mode')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'mode'   => $this->normalisePaymentMode($r->pm_raw),
                'amount' => (float) $r->total,
                'count'  => (int) $r->count,
            ])
            ->all();
    }

    private function topExpenseVouchers(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        return Expense::where('school_id', $schoolId)
            ->whereDate('expense_date', $date->toDateString())
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->with(['category', 'recordedBy'])
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->map(fn($e) => [
                'id'          => $e->id,
                'title'       => $e->title ?: $e->description ?: '—',
                'category'    => $e->category?->name ?? 'Uncategorised',
                'amount'      => (float) $e->amount,
                'mode'        => $this->normalisePaymentMode($e->payment_mode),
                'recorded_by' => $e->recordedBy?->name ?? '—',
            ])
            ->all();
    }

    // ───────────────────────────────────────────────────────────────
    // Cash flow
    // ───────────────────────────────────────────────────────────────
    private function cashFlow(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        $d = $date->toDateString();
        $apply = fn($q) => $q->when($academicYearId, fn($x) => $x->where('academic_year_id', $academicYearId));

        $cashIn = 0.0;
        foreach ([FeePayment::class, TransportFeePayment::class, HostelFeePayment::class, StationaryFeePayment::class] as $model) {
            $cashIn += (float) $apply($model::where('school_id', $schoolId)
                ->whereDate('payment_date', $d)
                ->where('amount_paid', '>', 0)
                ->where('payment_mode', 'cash'))
                ->sum('amount_paid');
        }

        $cashOut = (float) $apply(Expense::where('school_id', $schoolId)
            ->whereDate('expense_date', $d)
            ->where('payment_mode', 'cash'))
            ->sum('amount');

        return [
            'cash_in'  => $cashIn,
            'cash_out' => $cashOut,
            'net'      => $cashIn - $cashOut,
        ];
    }

    private function cashFlowInRange(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): array
    {
        $range = [$start->toDateString(), $end->toDateString()];
        $apply = fn($q) => $q->when($academicYearId, fn($x) => $x->where('academic_year_id', $academicYearId));

        $cashIn = 0.0;
        foreach ([FeePayment::class, TransportFeePayment::class, HostelFeePayment::class, StationaryFeePayment::class] as $model) {
            $cashIn += (float) $apply($model::where('school_id', $schoolId)
                ->whereBetween('payment_date', $range)
                ->where('amount_paid', '>', 0)
                ->where('payment_mode', 'cash'))
                ->sum('amount_paid');
        }

        $cashOut = (float) $apply(Expense::where('school_id', $schoolId)
            ->whereBetween('expense_date', $range)
            ->where('payment_mode', 'cash'))
            ->sum('amount');

        return ['cash_in' => $cashIn, 'cash_out' => $cashOut, 'net' => $cashIn - $cashOut];
    }

    // ───────────────────────────────────────────────────────────────
    // Highlights
    // ───────────────────────────────────────────────────────────────
    private function highlights(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        return [
            'student_of_the_day' => $this->longestPerfectAttendanceStudent($schoolId, $date, $academicYearId),
            'top_class'          => $this->topClassByAttendance($schoolId, $date, $academicYearId),
            'staff_shoutout'     => $this->longestPerfectStaffStreak($schoolId, $date),
        ];
    }

    private function longestPerfectAttendanceStudent(int $schoolId, Carbon $date, ?int $academicYearId): ?array
    {
        if (!$academicYearId) return null;

        // Look back 60 days. For each student count consecutive present days
        // from $date going backward — break on first absent / leave / no-record.
        $windowStart = $date->copy()->subDays(60);

        $rows = Attendance::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereBetween('date', [$windowStart->toDateString(), $date->toDateString()])
            ->orderByDesc('date')
            ->select('student_id', 'date', 'status')
            ->get()
            ->groupBy('student_id');

        $best = null;
        foreach ($rows as $studentId => $records) {
            $streak = 0;
            $cursor = $date->copy();
            $byDate = $records->keyBy(fn($r) => $r->date->toDateString());
            for ($i = 0; $i < 60; $i++) {
                $key = $cursor->toDateString();
                $rec = $byDate[$key] ?? null;
                if (!$rec) break;
                if (in_array($rec->status, ['present', 'late', 'half_day'], true)) {
                    $streak++;
                } elseif ($rec->status === 'holiday') {
                    // skip without breaking
                } else {
                    break;
                }
                $cursor->subDay();
            }
            if ($streak > 0 && (!$best || $streak > $best['streak'])) {
                $best = ['student_id' => $studentId, 'streak' => $streak];
            }
        }

        if (!$best) return null;

        $student = Student::with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->find($best['student_id']);
        if (!$student) return null;

        return [
            'name'    => trim($student->first_name . ' ' . $student->last_name),
            'class'   => $student->currentAcademicHistory?->courseClass?->name ?? '—',
            'section' => $student->currentAcademicHistory?->section?->name ?? null,
            'streak'  => $best['streak'],
        ];
    }

    private function topClassByAttendance(int $schoolId, Carbon $date, ?int $academicYearId): ?array
    {
        $rows = $this->classSectionAttendance($schoolId, $date, $academicYearId);
        if (empty($rows)) return null;

        // Filter to rows with at least 5 marked students for stability
        $eligible = array_values(array_filter($rows, fn($r) => $r['marked'] >= 5));
        if (empty($eligible)) return null;

        usort($eligible, fn($a, $b) => $b['pct'] <=> $a['pct'] ?: $b['present'] <=> $a['present']);
        $top = $eligible[0];

        return [
            'class'   => $top['class'],
            'section' => $top['section'],
            'pct'     => $top['pct'],
            'present' => $top['present'],
            'enrolled'=> $top['enrolled'],
        ];
    }

    private function longestPerfectStaffStreak(int $schoolId, Carbon $date): ?array
    {
        $windowStart = $date->copy()->subDays(60);

        $rows = StaffAttendance::where('school_id', $schoolId)
            ->whereBetween('date', [$windowStart->toDateString(), $date->toDateString()])
            ->orderByDesc('date')
            ->select('staff_id', 'date', 'status')
            ->get()
            ->groupBy('staff_id');

        $best = null;
        foreach ($rows as $staffId => $records) {
            $streak = 0;
            $cursor = $date->copy();
            $byDate = $records->keyBy(fn($r) => $r->date->toDateString());
            for ($i = 0; $i < 60; $i++) {
                $key = $cursor->toDateString();
                $rec = $byDate[$key] ?? null;
                if (!$rec) break;
                if ($rec->status === 'present') {
                    $streak++;
                } elseif ($rec->status === 'holiday') {
                    // skip without breaking
                } else {
                    break;
                }
                $cursor->subDay();
            }
            if ($streak > 0 && (!$best || $streak > $best['streak'])) {
                $best = ['staff_id' => $staffId, 'streak' => $streak];
            }
        }

        if (!$best) return null;

        $staff = Staff::with(['user', 'designation'])->find($best['staff_id']);
        if (!$staff) return null;

        return [
            'name'        => $staff->user?->name ?? '—',
            'designation' => $staff->designation?->name ?? null,
            'streak'      => $best['streak'],
        ];
    }

    // ───────────────────────────────────────────────────────────────
    // Admissions / events / outlook
    // ───────────────────────────────────────────────────────────────
    private function admissionsToday(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        $students = Student::where('school_id', $schoolId)
            ->whereDate('admission_date', $date->toDateString())
            ->when($academicYearId, fn($q) => $q->whereHas('academicHistories',
                fn($h) => $h->where('academic_year_id', $academicYearId)))
            ->with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->get();

        return [
            'count'    => $students->count(),
            'students' => $students->take(10)->map(fn($s) => [
                'name'         => trim($s->first_name . ' ' . $s->last_name),
                'admission_no' => $s->admission_no,
                'class'        => $s->currentAcademicHistory?->courseClass?->name ?? '—',
                'section'      => $s->currentAcademicHistory?->section?->name ?? null,
            ])->all(),
        ];
    }

    private function admissionsInRange(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): array
    {
        $count = Student::where('school_id', $schoolId)
            ->whereBetween('admission_date', [$start->toDateString(), $end->toDateString()])
            ->when($academicYearId, fn($q) => $q->whereHas('academicHistories',
                fn($h) => $h->where('academic_year_id', $academicYearId)))
            ->count();
        return ['count' => $count, 'students' => []];
    }

    private function dayEvents(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        $d = $date->toDateString();

        $visitors = VisitorLog::where('school_id', $schoolId)
            ->whereDate('in_time', $d)
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN out_time IS NOT NULL THEN 1 ELSE 0 END) as signed_out,
                SUM(CASE WHEN out_time IS NULL THEN 1 ELSE 0 END) as still_in
            ')->first();

        $birthdayStudents = Student::where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereMonth('dob', $date->month)
            ->whereDay('dob', $date->day)
            ->when($academicYearId, fn($q) => $q->whereHas('academicHistories',
                fn($h) => $h->where('academic_year_id', $academicYearId)))
            ->limit(20)->get(['id', 'first_name', 'last_name']);

        $holidays = Holiday::where('school_id', $schoolId)
            ->where('date', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->where('end_date', '>=', $d)->orWhereNull('end_date');
            })
            ->where('date', '<=', $d)
            ->get(['title', 'date', 'end_date', 'type']);

        return [
            'visitors' => [
                'total'      => (int) ($visitors->total ?? 0),
                'signed_out' => (int) ($visitors->signed_out ?? 0),
                'still_in'   => (int) ($visitors->still_in ?? 0),
            ],
            'birthdays' => $birthdayStudents->map(fn($s) => [
                'name' => trim($s->first_name . ' ' . $s->last_name),
            ])->all(),
            'holidays' => $holidays->map(fn($h) => [
                'title' => $h->title,
                'type'  => $h->type ?: 'holiday',
            ])->all(),
        ];
    }

    private function tomorrowOutlook(int $schoolId, Carbon $date, ?int $academicYearId): array
    {
        $tomorrow = $date->copy()->addDay();
        $td = $tomorrow->toDateString();

        $holidays = Holiday::where('school_id', $schoolId)
            ->where(function ($q) use ($td) {
                $q->where(function ($q2) use ($td) {
                    $q2->where('date', '<=', $td)->where('end_date', '>=', $td);
                })->orWhere('date', $td);
            })
            ->get(['title', 'type']);

        $birthdays = Student::where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereMonth('dob', $tomorrow->month)
            ->whereDay('dob', $tomorrow->day)
            ->when($academicYearId, fn($q) => $q->whereHas('academicHistories',
                fn($h) => $h->where('academic_year_id', $academicYearId)))
            ->count();

        return [
            'date'      => $td,
            'date_label'=> $tomorrow->format('l, d M Y'),
            'holidays'  => $holidays->map(fn($h) => $h->title . ($h->type ? " ({$h->type})" : ''))->all(),
            'birthdays' => $birthdays,
        ];
    }

    // ───────────────────────────────────────────────────────────────
    // Helpers — date-scoped fee / expense / attendance sums
    // ───────────────────────────────────────────────────────────────
    public function feeSumOnDay(int $schoolId, Carbon $date, ?int $academicYearId): float
    {
        $d = $date->toDateString();
        $sum = 0;
        foreach ([FeePayment::class, TransportFeePayment::class, HostelFeePayment::class, StationaryFeePayment::class] as $model) {
            $sum += (float) $model::where('school_id', $schoolId)
                ->whereDate('payment_date', $d)
                ->where('amount_paid', '>', 0)
                ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
                ->sum('amount_paid');
        }
        return $sum;
    }

    public function feeSumInRange(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): float
    {
        $sum = 0;
        foreach ([FeePayment::class, TransportFeePayment::class, HostelFeePayment::class, StationaryFeePayment::class] as $model) {
            $sum += (float) $model::where('school_id', $schoolId)
                ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
                ->where('amount_paid', '>', 0)
                ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
                ->sum('amount_paid');
        }
        return $sum;
    }

    public function expenseSumOnDay(int $schoolId, Carbon $date, ?int $academicYearId): float
    {
        return (float) Expense::where('school_id', $schoolId)
            ->whereDate('expense_date', $date->toDateString())
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->sum('amount');
    }

    public function expenseSumInRange(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): float
    {
        return (float) Expense::where('school_id', $schoolId)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->sum('amount');
    }

    private function attendancePct(int $schoolId, Carbon $date, ?int $academicYearId): float
    {
        $row = Attendance::where('school_id', $schoolId)
            ->where('date', $date->toDateString())
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->selectRaw('
                SUM(CASE WHEN status IN ("present","late","half_day") THEN 1 ELSE 0 END) as p,
                COUNT(*) as t
            ')->first();
        if (!$row || !$row->t) return 0.0;
        return round($row->p / $row->t * 100, 1);
    }

    private function avgAttendancePctRange(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): float
    {
        if ($start->gt($end)) return 0.0;
        $row = Attendance::where('school_id', $schoolId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->selectRaw('
                SUM(CASE WHEN status IN ("present","late","half_day") THEN 1 ELSE 0 END) as p,
                COUNT(*) as t
            ')->first();
        if (!$row || !$row->t) return 0.0;
        return round($row->p / $row->t * 100, 1);
    }

    private function weekAvgAttendancePct(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): float
    {
        return $this->avgAttendancePctRange($schoolId, $start, $end, $academicYearId);
    }

    private function weekDailyAttendance(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): array
    {
        $out = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $out[] = [
                'date'  => $cursor->toDateString(),
                'label' => $cursor->format('D'),
                'pct'   => $this->attendancePct($schoolId, $cursor, $academicYearId),
            ];
            $cursor->addDay();
        }
        return $out;
    }

    private function avgFeePerDayRange(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): float
    {
        if ($start->gt($end)) return 0.0;
        $days = $start->diffInDays($end) + 1;
        $total = $this->feeSumInRange($schoolId, $start, $end, $academicYearId);
        return $days > 0 ? round($total / $days, 2) : 0.0;
    }

    private function avgExpensePerDayRange(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): float
    {
        if ($start->gt($end)) return 0.0;
        $days = $start->diffInDays($end) + 1;
        $total = $this->expenseSumInRange($schoolId, $start, $end, $academicYearId);
        return $days > 0 ? round($total / $days, 2) : 0.0;
    }

    private function admissionsCountOnDay(int $schoolId, Carbon $date, ?int $academicYearId): float
    {
        return (float) Student::where('school_id', $schoolId)
            ->whereDate('admission_date', $date->toDateString())
            ->when($academicYearId, fn($q) => $q->whereHas('academicHistories',
                fn($h) => $h->where('academic_year_id', $academicYearId)))
            ->count();
    }

    private function admissionsCountInRange(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): float
    {
        return (float) Student::where('school_id', $schoolId)
            ->whereBetween('admission_date', [$start->toDateString(), $end->toDateString()])
            ->when($academicYearId, fn($q) => $q->whereHas('academicHistories',
                fn($h) => $h->where('academic_year_id', $academicYearId)))
            ->count();
    }

    private function avgAdmissionsPerDayRange(int $schoolId, Carbon $start, Carbon $end, ?int $academicYearId): float
    {
        if ($start->gt($end)) return 0.0;
        $days = $start->diffInDays($end) + 1;
        $total = $this->admissionsCountInRange($schoolId, $start, $end, $academicYearId);
        return $days > 0 ? round($total / $days, 2) : 0.0;
    }

    private function visitorsCountOnDay(int $schoolId, Carbon $date): float
    {
        return (float) VisitorLog::where('school_id', $schoolId)
            ->whereDate('in_time', $date->toDateString())
            ->count();
    }

    private function deltaPct(float $current, float $previous): ?float
    {
        if ($previous == 0.0) return $current > 0 ? null : 0.0;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Convert a payment_mode value (which can be a BackedEnum because the fee
     * models cast it to PaymentMode) into a plain string — safe to use as an
     * array key and to serialise to JSON.
     */
    private function normalisePaymentMode(mixed $raw): string
    {
        if ($raw instanceof \BackedEnum) return (string) $raw->value;
        if ($raw instanceof \UnitEnum)   return $raw->name;
        return ((string) ($raw ?? '')) ?: 'unknown';
    }
}
