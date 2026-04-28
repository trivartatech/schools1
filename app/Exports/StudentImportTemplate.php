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
        // Column order kept stable for schools using older templates: only
        // additions go on the right (qualification + guardian email/phone +
        // parent_address + birth_place + student_type + enrollment_type).
        // father_email / mother_email REMOVED — those columns don't exist on
        // the parents table; values were silently dropped.
        return [
            'admission_no', 'first_name', 'last_name', 'dob', 'birth_place', 'gender',
            'blood_group', 'religion', 'caste', 'category', 'mother_tongue',
            'nationality', 'aadhaar_no', 'address', 'city', 'state',
            'pincode', 'class', 'section', 'roll_no', 'admission_date',
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
        return [
            [
                'STU001', 'Rahul', 'Sharma', '2015-06-15', 'Delhi', 'Male',
                'B+', 'Hindu', '', 'General', 'Hindi',
                'Indian', '', '123 Main St', 'Delhi', 'Delhi',
                '110001', 'Class 5', 'A', '1', '2024-04-01',
                'New Student', 'Regular',
                'Ramesh Sharma', 'Sita Sharma', '', '9876543210',
                '9876543211', '9876543212',
                'Business', 'B.Sc',
                'Teacher', 'M.A',
                'parent@example.com', '9876543299',
                '123 Main St, Delhi 110001',
                'Uncle Kumar', '9876543213',
            ],
            [
                'STU002', 'Priya', 'Patel', '2014-11-20', 'Mumbai', 'Female',
                'O+', '', '', 'OBC', 'Gujarati',
                'Indian', '', '456 Park Ave', 'Mumbai', 'Maharashtra',
                '400001', 'Class 6', 'B', '2', '2024-04-01',
                'Old Student', 'Regular',
                'Suresh Patel', 'Meena Patel', '', '9123456780',
                '', '',
                'Engineer', 'B.E',
                'Homemaker', 'B.A',
                '', '',
                '456 Park Ave, Mumbai 400001',
                '', '',
            ],
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
