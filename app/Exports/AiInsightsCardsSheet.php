<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class AiInsightsCardsSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(private array $insights) {}

    public function title(): string
    {
        return 'Insights';
    }

    public function array(): array
    {
        $rows = [['Category', 'Severity', 'Metric', 'Trend', 'Title', 'Insight', 'Action']];

        foreach ($this->insights as $ins) {
            if (!is_array($ins)) continue;
            $rows[] = [
                $ins['category'] ?? '',
                $ins['severity'] ?? '',
                $ins['metric']   ?? '',
                $ins['trend']    ?? '',
                $ins['title']    ?? '',
                $ins['insight']  ?? '',
                $ins['action']   ?? '',
            ];
        }

        return $rows;
    }
}
