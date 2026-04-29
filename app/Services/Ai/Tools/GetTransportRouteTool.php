<?php

namespace App\Services\Ai\Tools;

use App\Models\TransportRoute;
use App\Models\TransportStudentAllocation;
use App\Models\TransportVehicle;
use App\Services\Ai\AiTool;

class GetTransportRouteTool extends AiTool
{
    public function name(): string
    {
        return 'get_transport_routes';
    }

    public function description(): string
    {
        return 'List active transport routes with their vehicle assignments and number of students. Use when the user asks about bus routes, vehicles, or transport allocation.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'route_id' => ['type' => 'integer', 'description' => 'Optional specific route id to look up'],
                'limit'    => ['type' => 'integer', 'description' => 'Maximum results (default 20, max 100)'],
            ],
            'required' => [],
        ];
    }

    public function run(array $args): array
    {
        $routeId = $args['route_id'] ?? null;
        $limit   = max(1, min(100, (int) ($args['limit'] ?? 20)));

        $routes = TransportRoute::where('school_id', $this->schoolId())
            ->when($routeId, fn($q) => $q->where('id', $routeId))
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return [
            'count'  => $routes->count(),
            'routes' => $routes->map(function ($r) {
                $studentCount = TransportStudentAllocation::where('school_id', $this->schoolId())
                    ->where('route_id', $r->id)
                    ->where('status', 'active')
                    ->count();
                $vehicleCount = TransportVehicle::where('school_id', $this->schoolId())
                    ->where('route_id', $r->id)
                    ->where('status', 'active')
                    ->count();
                return [
                    'id'             => $r->id,
                    'name'           => $r->name,
                    'student_count'  => $studentCount,
                    'vehicle_count'  => $vehicleCount,
                ];
            })->toArray(),
        ];
    }
}
