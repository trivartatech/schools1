<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class AiInsightsKpiSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(
        private array $snapshot,
        private $school,
        private Carbon $from,
        private Carbon $to
    ) {}

    public function title(): string
    {
        return 'KPIs';
    }

    public function array(): array
    {
        $rows = [
            ['Metric', 'Value'],
            ['School',         $this->school->name ?? ''],
            ['Range',          $this->from->toDateString() . ' to ' . $this->to->toDateString()],
            ['Generated at',   now()->format('Y-m-d H:i')],
            [],
            ['Section', 'Metric', 'Value'],
            ['Students',   'Total active',           $this->snapshot['students']['total']        ?? 0],
            ['Students',   'New in range',           $this->snapshot['students']['new_in_range'] ?? 0],
            ['Attendance', 'Marked today',           $this->snapshot['attendance']['total_marked'] ?? 0],
            ['Attendance', 'Present today',          $this->snapshot['attendance']['present_today'] ?? 0],
            ['Attendance', 'Attendance %',           $this->snapshot['attendance']['percentage'] ?? '—'],
            ['Attendance', 'Low-attendance students',count($this->snapshot['attendance']['low_attendance_students'] ?? [])],
            ['Fees',       'Collected today (₹)',    $this->snapshot['fees']['collected_today'] ?? 0],
            ['Fees',       'Collected in range (₹)', $this->snapshot['fees']['collected_in_range'] ?? 0],
            ['Fees',       'Total pending (₹)',      $this->snapshot['fees']['total_pending'] ?? 0],
            ['Fees',       'Overdue students',       $this->snapshot['fees']['overdue_students'] ?? 0],
            ['Staff',      'Total',                  $this->snapshot['staff']['total'] ?? 0],
            ['Staff',      'Present today',          $this->snapshot['staff']['present_today'] ?? 0],
            ['Staff',      'Attendance %',           $this->snapshot['staff']['attendance_pct'] ?? '—'],
        ];

        if (!empty($this->snapshot['exams'])) {
            $rows[] = ['Exams', 'Average %',  $this->snapshot['exams']['avg_percentage'] ?? '—'];
            $rows[] = ['Exams', 'Pass rate %',$this->snapshot['exams']['pass_rate']      ?? '—'];
        }
        if (!empty($this->snapshot['hostel'])) {
            $rows[] = ['Hostel', 'Total beds',     $this->snapshot['hostel']['total_beds']    ?? 0];
            $rows[] = ['Hostel', 'Occupied',       $this->snapshot['hostel']['occupied']      ?? 0];
            $rows[] = ['Hostel', 'Occupancy %',    $this->snapshot['hostel']['occupancy_pct'] ?? '—'];
        }
        if (!empty($this->snapshot['transport'])) {
            $rows[] = ['Transport', 'Students',         $this->snapshot['transport']['students']         ?? 0];
            $rows[] = ['Transport', 'Active vehicles',  $this->snapshot['transport']['active_vehicles']  ?? 0];
            $rows[] = ['Transport', 'Expiring docs',    $this->snapshot['transport']['expiring_docs']    ?? 0];
        }

        return $rows;
    }
}
