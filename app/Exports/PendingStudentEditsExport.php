<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PendingStudentEditsExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return array_map(fn($r) => [
            $r['admission_no'],
            $r['student_name'],
            $r['class'],
            $r['section'],
            $r['field'],
            $r['old_value'],
            $r['new_value'],
            $r['requested_by'],
            $r['requested_at'],
            $r['reason'],
        ], $this->rows);
    }

    public function headings(): array
    {
        return [
            'Admission No',
            'Student Name',
            'Class',
            'Section',
            'Field',
            'Old Value',
            'New Value',
            'Requested By',
            'Requested At',
            'Reason',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
