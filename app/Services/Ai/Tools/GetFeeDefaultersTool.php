<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AiTool;
use App\Services\FeeService;

class GetFeeDefaultersTool extends AiTool
{
    public function name(): string
    {
        return 'get_fee_defaulters';
    }

    public function description(): string
    {
        return 'List the top fee defaulters for the current academic year, ordered by outstanding balance descending. Use when the user asks who hasn\'t paid fees.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'description' => 'Number of defaulters to return (default 10, max 50)'],
            ],
            'required' => [],
        ];
    }

    public function run(array $args): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 10)));

        $pending = app(FeeService::class)->getSchoolPendingFees($this->schoolId(), $this->academicYearId());

        $defaulters = collect($pending['pending_fee_students'] ?? [])
            ->sortByDesc('balance')
            ->take($limit)
            ->map(fn($r) => [
                'student' => $r['student'] ?? null,
                'class'   => $r['class']   ?? null,
                'balance' => round((float) ($r['balance'] ?? 0), 2),
            ])
            ->values()
            ->all();

        return [
            'total_pending_amount'   => round((float) ($pending['pending_fees'] ?? 0), 2),
            'total_defaulter_count'  => count($pending['pending_fee_students'] ?? []),
            'top_defaulters'         => $defaulters,
        ];
    }
}
