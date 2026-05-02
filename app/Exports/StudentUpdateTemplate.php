<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentUpdateTemplate implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
        // Update template — every column is optional; blank cells are
        // ignored. Same field set as the create template plus erp_no
        // (lookup key) and status. father_email / mother_email REMOVED
        // — those columns don't exist on parents and were silently dropped.
        return [
            'erp_no', 'admission_no', 'first_name', 'last_name', 'dob', 'birth_place', 'gender',
            'blood_group', 'religion', 'caste', 'category', 'mother_tongue',
            'nationality', 'aadhaar_no', 'address', 'city', 'state',
            'pincode', 'class', 'section', 'roll_no', 'status',
            'student_type', 'enrollment_type',
            'father_name', 'mother_name', 'guardian_name', 'phone',
            'father_phone', 'mother_phone',
            'father_occupation', 'father_qualification',
            'mother_occupation', 'mother_qualification',
            'guardian_email', 'guardian_phone',
            'parent_address',
            'emergency_contact_name', 'emergency_contact_phone',
        ];
    }

    public function array(): array
    {
        // Two example rows: address-only update + parent-only update
        return [
            [
                'ERP_2025-26_0001', '', '', '', '', '', '',
                '', '', '', '', '',
                '', '', '456 New Address', 'Mumbai', 'Maharashtra',
                '400001', 'Class 6', 'A', '5', '',
                'Old Student', '',
                '', '', '', '9876543999',
                '', '',
                '', '',
                '', '',
                '', '',
                '',
                '', '',
            ],
            [
                '', 'STU002', '', 'Verma', '', '', '',
                '', '', '', '', '',
                '', '', '', '', '',
                '', '', '', '', 'Active',
                '', '',
                'Suresh Verma', '', '', '',
                '', '',
                '', 'M.Sc',
                '', '',
                'newparent@example.com', '9988776655',
                '789 Updated Lane',
                '', '',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D97706']],
        ]);

        $sheet->getStyle("A2:{$lastCol}3")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '9CA3AF']],
        ]);

        $lastColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);
        for ($i = 1; $i <= $lastColIndex; $i++) {
            $sheet->getColumnDimension(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i)
            )->setAutoSize(true);
        }

        return [];
    }
}
