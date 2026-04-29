<?php

namespace App\Services\Ai\Tools;

use App\Models\Leave;
use App\Services\Ai\AiTool;

class GetStaffOnLeaveTool extends AiTool
{
    public function name(): string
    {
        return 'get_staff_on_leave';
    }

    public function description(): string
    {
        return 'List staff currently on approved leave, or pending leave requests. Use when the user asks about staff leave status.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['approved', 'pending', 'all'], 'description' => 'Filter by leave status (default: approved on or after today)'],
                'limit'  => ['type' => 'integer', 'description' => 'Maximum results (default 20, max 100)'],
            ],
            'required' => [],
        ];
    }

    public function run(array $args): array
    {
        $status = $args['status'] ?? 'approved';
        $limit  = max(1, min(100, (int) ($args['limit'] ?? 20)));
        $today  = now()->toDateString();

        $query = Leave::where('school_id', $this->schoolId())
            ->with(['staff:id,first_name,last_name,emp_no'])
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($status === 'approved', fn($q) => $q->where('end_date', '>=', $today))
            ->orderBy('start_date', 'desc');

        $rows = $query->limit($limit)->get();

        return [
            'count'  => $rows->count(),
            'leaves' => $rows->map(function ($l) {
                $staffName = $l->staff ? trim(($l->staff->first_name ?? '') . ' ' . ($l->staff->last_name ?? '')) : 'Unknown';
                return [
                    'leave_id'   => $l->id,
                    'staff_name' => $staffName,
                    'emp_no'     => $l->staff->emp_no ?? null,
                    'start_date' => optional($l->start_date)->toDateString(),
                    'end_date'   => optional($l->end_date)->toDateString(),
                    'status'     => $l->status,
                ];
            })->toArray(),
        ];
    }
}
