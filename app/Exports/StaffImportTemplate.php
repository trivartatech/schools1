<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StaffImportTemplate implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return [
            'name', 'email', 'phone', 'username', 'password', 'employee_id', 'role',
            'department', 'designation', 'qualification', 'experience_years',
            'joining_date', 'basic_salary', 'bank_name', 'bank_account_no',
            'ifsc_code', 'pan_no', 'epf_no',
        ];
    }

    public function array(): array
    {
        return [
            ['Rajesh Kumar', 'rajesh@example.com', '9876543210', 'rajesh.kumar', 'Pass@1234', 'EMP001', 'teacher', 'Mathematics', 'Senior Teacher', 'M.Sc Mathematics', '8', '2024-04-01', '45000', 'State Bank', '12345678901234', 'SBIN0001234', 'ABCDE1234F', 'MH/BOM/12345'],
            ['Anita Singh', 'anita@example.com', '9123456780', '', '', 'EMP002', 'accountant', 'Accounts', 'Accountant', 'M.Com', '5', '2024-06-15', '35000', 'HDFC Bank', '98765432101234', 'HDFC0004321', 'FGHIJ5678K', ''],
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

        $lastColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);
        for ($i = 1; $i <= $lastColIndex; $i++) {
            $sheet->getColumnDimension(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i)
            )->setAutoSize(true);
        }

        return [];
    }
}
