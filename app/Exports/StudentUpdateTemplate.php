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
        return [
            'erp_no', 'admission_no', 'first_name', 'last_name', 'dob', 'gender',
            'blood_group', 'religion', 'caste', 'category', 'mother_tongue',
            'nationality', 'aadhaar_no', 'address', 'city', 'state',
            'pincode', 'class', 'section', 'roll_no', 'status',
            'father_name', 'mother_name', 'guardian_name', 'phone',
            'father_phone', 'mother_phone', 'father_occupation', 'mother_occupation',
            'father_email', 'mother_email', 'emergency_contact_name', 'emergency_contact_phone',
        ];
    }

    public function array(): array
    {
        return [
            ['2025-26/0001', '', '', '', '', '', '', '', '', '', '', '', '', '456 New Address', 'Mumbai', 'Maharashtra', '400001', 'Class 6', 'A', '5', '', '', '', '', '9876543999', '', '', '', '', '', '', '', ''],
            ['', 'STU002', '', 'Verma', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Active', 'Suresh Verma', '', '', '', '', '', '', '', '', '', '', ''],
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

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
