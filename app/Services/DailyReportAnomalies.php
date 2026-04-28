<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\DailyReportSetting;
use App\Models\Expense;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\VisitorLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Detects anomalies for the daily report — things that need an admin's
 * attention. Each rule produces zero-or-more typed alert records.
 */
class DailyReportAnomalies
{
    public function detect(int $schoolId, Carbon $date, ?int $academicYearId, DailyReportSetting $settings): array
    {
        $alerts = [];

        $alerts = array_merge($alerts, $this->lowAttendanceClasses($schoolId, $date, $academicYearId, $settings->low_attendance_threshold_pct));
        $alerts = array_merge($alerts, $this->repeatAbsentees($schoolId, $date, $academicYearId, $settings->repeat_absent_days));
        $alerts = array_merge($alerts, $this->oversizedExpenses($schoolId, $date, $academicYearId, (float) $settings->oversized_expense_threshold));
        $alerts = array_merge($alerts, $this->staleVisitors($schoolId, $date));

        return $alerts;
    }

    /**
     * Class+section pairs whose effective-present % is below threshold.
     * Only flags pairs where ≥5 students are marked (avoid noise on tiny groups).
     */
    private function lowAttendanceClasses(int $schoolId, Carbon $date, ?int $academicYearId, int $thresholdPct): array
    {
        if (!$academicYearId) return [];

        $rows = Attendance::where('attendances.school_id', $schoolId)
            ->where('attendances.date', $date->toDateString())
            ->where('attendances.academic_year_id', $academicYearId)
            ->join('course_classes as cc', 'attendances.class_id', '=', 'cc.id')
            ->leftJoin('sections as s', 'attendances.section_id', '=', 's.id')
            ->selectRaw('
                cc.id as class_id, cc.name as class_name,
                s.id as section_id, s.name as section_name,
                COUNT(*) as total,
                SUM(CASE WHEN attendances.status = "present"  THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN attendances.status = "late"     THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN attendances.status = "half_day" THEN 1 ELSE 0 END) as halfday_count
            ')
            ->groupBy('cc.id', 'cc.name', 's.id', 's.name')
            ->havingRaw('COUNT(*) >= 5')
            ->get();

        $flagged = [];
        foreach ($rows as $r) {
            $effectivePresent = $r->present_count + ($r->late_count * 0.5) + ($r->halfday_count * 0.5);
            $pct = $r->total > 0 ? round($effectivePresent / $r->total * 100, 1) : 0;
            if ($pct < $thresholdPct) {
                $flagged[] = [
                    'class'    => $r->class_name,
                    'section'  => $r->section_name,
                    'present'  => (int) ($r->present_count + $r->late_count + $r->halfday_count),
                    'total'    => (int) $r->total,
                    'pct'      => $pct,
                ];
            }
        }

        if (empty($flagged)) return [];

        usort($flagged, fn($a, $b) => $a['pct'] <=> $b['pct']);

        return [[
            'type'     => 'low_attendance_classes',
            'severity' => 'amber',
            'label'    => 'Classes below ' . $thresholdPct . '% attendance',
            'count'    => count($flagged),
            'items'    => array_slice($flagged, 0, 10),
        ]];
    }

    /**
     * Students absent today AND each of the previous N-1 school days.
     * Looks back $repeatDays days (including today). Skips holidays.
     */
    private function repeatAbsentees(int $schoolId, Carbon $date, ?int $academicYearId, int $repeatDays): array
    {
        if (!$academicYearId) return [];
        $repeatDays = max(2, $repeatDays);

        // Pull last $repeatDays days of attendance (ignore holidays)
        $start = $date->copy()->subDays($repeatDays * 2)->toDateString();
        $end   = $date->toDateString();

        $rows = Attendance::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereBetween('date', [$start, $end])
            ->whereNotIn('status', ['holiday'])
            ->select('student_id', 'date', 'status')
            ->get()
            ->groupBy('student_id');

        $flagged = [];
        foreach ($rows as $studentId => $records) {
            // Sort by date desc, take the most recent $repeatDays non-holiday days
            $sorted = $records->sortByDesc(fn($r) => $r->date->toDateString())->values();
            if ($sorted->count() < $repeatDays) continue;
            $window = $sorted->take($repeatDays);

            $allAbsent = $window->every(fn($r) => $r->status === 'absent');
            if ($allAbsent) {
                $flagged[$studentId] = $window->count();
            }
        }

        if (empty($flagged)) return [];

        $students = Student::with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->whereIn('id', array_keys($flagged))
            ->limit(15)
            ->get();

        $items = $students->map(fn($s) => [
            'name'    => trim($s->first_name . ' ' . $s->last_name),
            'class'   => $s->currentAcademicHistory?->courseClass?->name ?? '—',
            'section' => $s->currentAcademicHistory?->section?->name ?? null,
            'days'    => $flagged[$s->id] ?? $repeatDays,
        ])->all();

        return [[
            'type'     => 'repeat_absentees',
            'severity' => 'red',
            'label'    => 'Students absent ' . $repeatDays . '+ days running',
            'count'    => count($flagged),
            'items'    => $items,
        ]];
    }

    /**
     * Expense vouchers above the configured threshold (default ₹50,000).
     */
    private function oversizedExpenses(int $schoolId, Carbon $date, ?int $academicYearId, float $threshold): array
    {
        $rows = Expense::where('school_id', $schoolId)
            ->whereDate('expense_date', $date->toDateString())
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->where('amount', '>=', $threshold)
            ->with(['category', 'recordedBy'])
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) return [];

        return [[
            'type'     => 'oversized_expenses',
            'severity' => 'amber',
            'label'    => 'Expense vouchers ≥ ₹' . number_format($threshold, 0),
            'count'    => $rows->count(),
            'items'    => $rows->map(fn($e) => [
                'title'       => $e->title ?: $e->description ?: '—',
                'category'    => $e->category?->name ?? 'Uncategorised',
                'amount'      => (float) $e->amount,
                'recorded_by' => $e->recordedBy?->name ?? '—',
            ])->all(),
        ]];
    }

    /**
     * Visitors who came in today but never signed out (security flag at EOD).
     */
    private function staleVisitors(int $schoolId, Carbon $date): array
    {
        $count = VisitorLog::where('school_id', $schoolId)
            ->whereDate('in_time', $date->toDateString())
            ->whereNull('out_time')
            ->count();

        if ($count === 0) return [];

        $sample = VisitorLog::where('school_id', $schoolId)
            ->whereDate('in_time', $date->toDateString())
            ->whereNull('out_time')
            ->orderByDesc('in_time')
            ->limit(5)
            ->get(['name', 'purpose', 'in_time']);

        return [[
            'type'     => 'stale_visitors',
            'severity' => 'amber',
            'label'    => 'Visitors still inside (no sign-out)',
            'count'    => $count,
            'items'    => $sample->map(fn($v) => [
                'name'    => $v->name,
                'purpose' => $v->purpose,
                'in_time' => $v->in_time?->format('h:i A'),
            ])->all(),
        ]];
    }
}
