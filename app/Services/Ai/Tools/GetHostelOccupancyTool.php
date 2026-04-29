<?php

namespace App\Services\Ai\Tools;

use App\Models\HostelBed;
use App\Services\Ai\AiTool;
use Illuminate\Support\Facades\DB;

class GetHostelOccupancyTool extends AiTool
{
    public function name(): string
    {
        return 'get_hostel_occupancy';
    }

    public function description(): string
    {
        return 'Get hostel bed occupancy summary: total beds, occupied, available, and occupancy percentage.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function run(array $args): array
    {
        $rows = HostelBed::where('school_id', $this->schoolId())
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $total     = array_sum($rows);
        $occupied  = (int) ($rows['Occupied']    ?? 0);
        $available = (int) ($rows['Available']   ?? 0);
        $maint     = (int) ($rows['Maintenance'] ?? 0);

        return [
            'total_beds'    => $total,
            'occupied'      => $occupied,
            'available'     => $available,
            'maintenance'   => $maint,
            'occupancy_pct' => $total > 0 ? round($occupied / $total * 100, 1) : null,
        ];
    }
}
