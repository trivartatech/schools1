<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentImportTemplate implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return [
            'admission_no', 'first_name', 'last_name', 'dob', 'gender',
            'blood_group', 'religion', 'caste', 'category', 'mother_tongue',
            'nationality', 'aadhaar_no', 'address', 'city', 'state',
            'pincode', 'class', 'section', 'roll_no', 'admission_date',
            'father_name', 'mother_name', 'guardian_name', 'phone',
            'father_phone', 'mother_phone', 'father_occupation', 'mother_occupation',
            'father_email', 'mother_email', 'emergency_contact_name', 'emergency_contact_phone',
        ];
    }

    public function array(): array
    {
        return [
            ['STU001', 'Rahul', 'Sharma', '2015-06-15', 'Male', 'B+', 'Hindu', '', 'General', 'Hindi', 'Indian', '', '123 Main St', 'Delhi', 'Delhi', '110001', 'Class 5', 'A', '1', '2024-04-01', 'Ramesh Sharma', 'Sita Sharma', '', '9876543210', '9876543211', '9876543212', 'Business', 'Teacher', 'ramesh@example.com', 'sita@example.com', 'Uncle Kumar', '9876543213'],
            ['STU002', 'Priya', 'Patel', '2014-11-20', 'Female', 'O+', '', '', 'OBC', 'Gujarati', 'Indian', '', '456 Park Ave', 'Mumbai', 'Maharashtra', '400001', 'Class 6', 'B', '2', '2024-04-01', 'Suresh Patel', 'Meena Patel', '', '9123456780', '', '', 'Engineer', 'Homemaker', '', '', '', ''],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
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
