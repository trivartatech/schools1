<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AiInsightsExport implements WithMultipleSheets
{
    public function __construct(
        private array $snapshot,
        private array $insights,
        private $school,
        private Carbon $from,
        private Carbon $to
    ) {}

    public function sheets(): array
    {
        return [
            'KPIs'      => new AiInsightsKpiSheet($this->snapshot, $this->school, $this->from, $this->to),
            'Insights'  => new AiInsightsCardsSheet($this->insights),
            'Top Dues'  => new AiInsightsTopDuesSheet($this->snapshot),
        ];
    }
}
