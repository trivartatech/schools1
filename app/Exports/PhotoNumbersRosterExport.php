<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Roster export for the Photo Numbers page — one row per student showing
 * the photo number plus every field editable in the inline modal. Used by
 * the operator to cross-check student details against what the photographer
 * sees during an ID-card session.
 */
class PhotoNumbersRosterExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return array_map(fn($r) => [
            $r['admission_no'],
            $r['erp_no'] ?? '',
            $r['photo_number'] ?? '',
            $r['name'],
            $r['class'] ?? '',
            $r['section'] ?? '',
            $r['student_address'] ?? '',
            $r['primary_phone'] ?? '',
            $r['father_name'] ?? '',
            $r['father_phone'] ?? '',
            $r['mother_name'] ?? '',
            $r['mother_phone'] ?? '',
            $r['parent_address'] ?? '',
            $r['pending_changes_count'] > 0 ? "Yes ({$r['pending_changes_count']})" : 'No',
        ], $this->rows);
    }

    public function headings(): array
    {
        return [
            'Admission No',
            'ERP No',
            'Photo Number',
            'Student Name',
            'Class',
            'Section',
            'Student Address',
            'Primary Phone',
            'Father Name',
            'Father Phone',
            'Mother Name',
            'Mother Phone',
            'Parent Address',
            'Has Pending Edits',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
