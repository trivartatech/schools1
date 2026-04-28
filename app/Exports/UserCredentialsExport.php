<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserCredentialsExport implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    protected array $rows;
    protected string $title;

    public function __construct(array $rows, string $title = 'User Credentials')
    {
        $this->rows  = $rows;
        $this->title = $title;
    }

    public function array(): array
    {
        return array_map(fn($r) => [
            $r['name']         ?? '',
            $r['role']         ?? '',
            $r['class_name']   ?? '',
            $r['section_name'] ?? '',
            $r['username']     ?? '',
            $r['password']     ?? '',
        ], $this->rows);
    }

    public function headings(): array
    {
        return ['Name', 'Role', 'Class', 'Section', 'Username', 'Password'];
    }

    public function title(): string
    {
        return 'Credentials';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1565C0'],
                ],
            ],
        ];
    }
}
