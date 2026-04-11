<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ListExport implements FromArray, ShouldAutoSize, WithStyles
{
    protected array $rows;
    protected bool $hasFooter;

    public function __construct(array $rows, bool $hasFooter = false)
    {
        $this->rows = $rows;
        $this->hasFooter = $hasFooter;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            1 => [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE9ECF1'],
                ],
            ],
        ];

        if ($this->hasFooter) {
            $lastRow = count($this->rows);
            $styles[$lastRow] = ['font' => ['bold' => true]];
        }

        return $styles;
    }
}
