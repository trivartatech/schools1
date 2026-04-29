<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class AiInsightsTopDuesSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(private array $snapshot) {}

    public function title(): string
    {
        return 'Top Dues & Low Attendance';
    }

    public function array(): array
    {
        $rows = [['Section', 'Name', 'Value']];

        foreach ($this->snapshot['fees']['top_due_students'] ?? [] as $r) {
            $rows[] = ['Top Defaulter', $r['name'] ?? '', '₹' . number_format($r['due'] ?? 0, 0)];
        }
        foreach ($this->snapshot['attendance']['low_attendance_students'] ?? [] as $r) {
            $rows[] = ['Low Attendance', $r['name'] ?? '', ($r['percentage'] ?? '') . '%'];
        }

        return $rows;
    }
}
