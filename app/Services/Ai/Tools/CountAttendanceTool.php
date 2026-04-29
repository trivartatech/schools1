<?php

namespace App\Services\Ai\Tools;

use App\Models\Attendance;
use App\Services\Ai\AiTool;
use Illuminate\Support\Facades\DB;

class CountAttendanceTool extends AiTool
{
    public function name(): string
    {
        return 'count_attendance';
    }

    public function description(): string
    {
        return 'Count student attendance for a given date (or today by default). Returns counts grouped by status: present, absent, late, half_day. Optional class/section filter.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date'       => ['type' => 'string', 'description' => 'Date in YYYY-MM-DD format. Defaults to today.'],
                'class_id'   => ['type' => 'integer', 'description' => 'Optional class id to filter by'],
                'section_id' => ['type' => 'integer', 'description' => 'Optional section id to filter by'],
            ],
            'required' => [],
        ];
    }

    public function run(array $args): array
    {
        $date      = $args['date'] ?? now()->toDateString();
        $classId   = $args['class_id']   ?? null;
        $sectionId = $args['section_id'] ?? null;

        $rows = Attendance::where('school_id', $this->schoolId())
            ->when($this->academicYearId(), fn($q) => $q->where('academic_year_id', $this->academicYearId()))
            ->where('date', $date)
            ->when($classId, fn($q) => $q->where('class_id', $classId))
            ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $present = (int)($rows['present'] ?? 0) + (int)($rows['late'] ?? 0) + (int)($rows['half_day'] ?? 0);
        $marked  = array_sum($rows);
        $pct     = $marked > 0 ? round($present / $marked * 100, 1) : null;

        return [
            'date'           => $date,
            'class_id'       => $classId,
            'section_id'     => $sectionId,
            'by_status'      => $rows,
            'total_marked'   => $marked,
            'present_total'  => $present,
            'absent_total'   => (int) ($rows['absent'] ?? 0),
            'attendance_pct' => $pct,
        ];
    }
}
