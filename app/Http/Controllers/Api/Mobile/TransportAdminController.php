<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\TransportRoute;
use App\Models\TransportStudentAllocation;
use App\Models\TransportVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Mobile Transport admin endpoints (read-only for v1).
 *
 * The driver-side transport endpoints (live tracking, boarding, etc.) live
 * on MobileApiController and are unchanged. This controller is the admin
 * counterpart so principals/accountants can see the fleet, route layouts,
 * and student allocations on the go.
 */
class TransportAdminController extends Controller
{
    private function assertTransportAdmin(Request $request): void
    {
        $user = $request->user();
        $type = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        // Drivers, parents and students should not hit the admin transport API;
        // drivers have their own dedicated endpoints.
        if (!in_array($type, ['admin', 'school_admin', 'principal', 'super_admin', 'staff', 'accountant'], true)) {
            abort(response()->json(['error' => 'Unauthorized.'], 403));
        }
    }

    /** Return a "Days until expiry" value, or null when no date is set. */
    private function daysUntil(?string $dateStr): ?int
    {
        if (!$dateStr) return null;
        try {
            $d = Carbon::parse($dateStr)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
        return (int) Carbon::today()->diffInDays($d, false);
    }

    private function vehiclePayload(TransportVehicle $v, bool $withCompliance = true): array
    {
        $row = [
            'id'             => $v->id,
            'vehicle_number' => $v->vehicle_number,
            'vehicle_name'   => $v->vehicle_name,
            'capacity'       => $v->capacity,
            'status'         => $v->status,
            'driver'         => $v->driver ? [
                'id'    => $v->driver->id,
                'name'  => $v->driver->user?->name
                    ?? trim(($v->driver->first_name ?? '') . ' ' . ($v->driver->last_name ?? '')),
                'phone' => $v->driver->phone ?? $v->driver->user?->phone,
            ] : null,
            'conductor'      => $v->conductor ? [
                'id'   => $v->conductor->id,
                'name' => $v->conductor->user?->name
                    ?? trim(($v->conductor->first_name ?? '') . ' ' . ($v->conductor->last_name ?? '')),
            ] : ($v->conductor_name ? ['id' => null, 'name' => $v->conductor_name] : null),
            'route'          => $v->route ? [
                'id'        => $v->route->id,
                'route_name'=> $v->route->route_name,
                'route_code'=> $v->route->route_code,
            ] : null,
        ];

        if ($withCompliance) {
            $row['compliance'] = [
                'insurance_expiry'  => $v->insurance_expiry instanceof \Carbon\Carbon
                    ? $v->insurance_expiry->toDateString() : (is_string($v->insurance_expiry) ? substr($v->insurance_expiry, 0, 10) : null),
                'fitness_expiry'    => $v->fitness_expiry instanceof \Carbon\Carbon
                    ? $v->fitness_expiry->toDateString() : (is_string($v->fitness_expiry) ? substr($v->fitness_expiry, 0, 10) : null),
                'pollution_expiry'  => $v->pollution_expiry instanceof \Carbon\Carbon
                    ? $v->pollution_expiry->toDateString() : (is_string($v->pollution_expiry) ? substr($v->pollution_expiry, 0, 10) : null),
                'days_until_insurance' => $this->daysUntil($v->insurance_expiry?->toDateString() ?? $v->insurance_expiry),
                'days_until_fitness'   => $this->daysUntil($v->fitness_expiry?->toDateString() ?? $v->fitness_expiry),
                'days_until_pollution' => $this->daysUntil($v->pollution_expiry?->toDateString() ?? $v->pollution_expiry),
            ];

            $live = $v->liveLocation;
            $row['live_location'] = $live ? [
                'latitude'  => (float) $live->latitude,
                'longitude' => (float) $live->longitude,
                'speed'     => $live->speed !== null ? (float) $live->speed : null,
                'updated_at'=> $live->updated_at?->toIso8601String(),
            ] : null;
        }

        return $row;
    }

    /**
     * GET /mobile/transport/admin/routes
     *
     * Routes list with stop / vehicle / allocation counts and a one-line
     * vehicle summary. Heavy data (full stops, full vehicle details) is on
     * the route-detail endpoint to keep this list cheap.
     *
     * Query params:
     *   status   'active' | 'inactive'  (optional)
     *   search   substring on route_name or route_code
     */
    public function routes(Request $request): JsonResponse
    {
        $this->assertTransportAdmin($request);
        $schoolId = app('current_school_id');

        $query = TransportRoute::where('school_id', $schoolId)
            ->withCount(['stops', 'vehicles', 'studentAllocations as allocations_count' => fn($q) => $q->where('status', 'active')])
            ->with(['vehicles:id,route_id,vehicle_number,vehicle_name,status']);

        if ($status = $request->input('status')) {
            if (in_array($status, ['active', 'inactive'], true)) {
                $query->where('status', $status);
            }
        }
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('route_name', 'like', "%{$search}%")
                  ->orWhere('route_code', 'like', "%{$search}%");
            });
        }

        $routes = $query->orderBy('route_name')->get();

        $data = $routes->map(function ($r) {
            $vehicles = $r->vehicles ?? collect();
            $first    = $vehicles->first();
            return [
                'id'                => $r->id,
                'route_name'        => $r->route_name,
                'route_code'        => $r->route_code,
                'start_location'    => $r->start_location,
                'end_location'      => $r->end_location,
                'distance'          => $r->distance,
                'estimated_time'    => $r->estimated_time,
                'status'            => $r->status,
                'stops_count'       => (int) ($r->stops_count       ?? 0),
                'vehicles_count'    => (int) ($r->vehicles_count    ?? 0),
                'allocations_count' => (int) ($r->allocations_count ?? 0),
                'vehicle_summary'   => $vehicles->isEmpty()
                    ? null
                    : ($vehicles->count() === 1
                        ? ($first->vehicle_number . ($first->vehicle_name ? " · {$first->vehicle_name}" : ''))
                        : "{$first->vehicle_number} +" . ($vehicles->count() - 1) . ' more'),
            ];
        })->values();

        return response()->json([
            'data'    => $data,
            'summary' => [
                'total_routes'   => $routes->count(),
                'active_routes'  => $routes->where('status', 'active')->count(),
                'total_students' => (int) $routes->sum('allocations_count'),
            ],
        ]);
    }

    /**
     * GET /mobile/transport/admin/routes/{id}
     *
     * Full route detail — stops in order, vehicles assigned, and active
     * student allocations grouped by stop so the UI can render a
     * collapsible per-stop list.
     */
    public function routeDetail(Request $request, int $id): JsonResponse
    {
        $this->assertTransportAdmin($request);
        $schoolId = app('current_school_id');

        $route = TransportRoute::where('school_id', $schoolId)
            ->with([
                'stops' => fn($q) => $q->orderBy('stop_order')->orderBy('id'),
                'vehicles.driver.user',
                'vehicles.conductor.user',
                'vehicles.liveLocation',
            ])
            ->find($id);

        if (!$route) {
            return response()->json(['error' => 'Route not found.'], 404);
        }

        // Pull active allocations for this route in one query and group by stop.
        $allocations = TransportStudentAllocation::where('school_id', $schoolId)
            ->where('route_id', $route->id)
            ->where('status', 'active')
            ->with([
                'student:id,first_name,last_name,admission_no,parent_id',
                'student.studentParent:id,father_phone,mother_phone',
                'student.currentAcademicHistory.courseClass:id,name',
                'student.currentAcademicHistory.section:id,name',
            ])
            ->get()
            ->groupBy('stop_id');

        $stops = $route->stops->map(function ($stop) use ($allocations) {
            $stopAllocs = $allocations->get($stop->id, collect());
            return [
                'id'            => $stop->id,
                'stop_name'     => $stop->stop_name,
                'stop_code'     => $stop->stop_code,
                'pickup_time'   => $stop->pickup_time,
                'drop_time'     => $stop->drop_time,
                'distance_from_school' => $stop->distance_from_school,
                'fee'           => $stop->fee,
                'stop_order'    => $stop->stop_order,
                'latitude'      => $stop->latitude,
                'longitude'     => $stop->longitude,
                'students'      => $stopAllocs->map(function ($a) {
                    $s    = $a->student;
                    $hist = $s?->currentAcademicHistory;
                    $cls  = $hist?->courseClass?->name;
                    $sec  = $hist?->section?->name;
                    return [
                        'allocation_id' => $a->id,
                        'student_id'    => $s?->id,
                        'name'          => $s ? trim($s->first_name . ' ' . $s->last_name) : null,
                        'admission_no'  => $s?->admission_no,
                        'class'         => trim(($cls ?? '') . ($sec ? " - {$sec}" : ''), ' -'),
                        'pickup_type'   => $a->pickup_type,
                        'father_phone'  => $s?->studentParent?->father_phone,
                        'mother_phone'  => $s?->studentParent?->mother_phone,
                        'payment_status'=> $a->payment_status ?? null,
                        'balance'       => $a->balance,
                    ];
                })->values(),
                'students_count' => $stopAllocs->count(),
            ];
        })->values();

        $vehicles = $route->vehicles->map(fn($v) => $this->vehiclePayload($v, true))->values();

        $totalStudents = $stops->sum('students_count');
        $totalCapacity = (int) $route->vehicles->sum('capacity');
        $occupancyPct  = $totalCapacity > 0 ? round(($totalStudents / $totalCapacity) * 100, 1) : null;

        return response()->json([
            'data' => [
                'route' => [
                    'id'             => $route->id,
                    'route_name'     => $route->route_name,
                    'route_code'     => $route->route_code,
                    'start_location' => $route->start_location,
                    'end_location'   => $route->end_location,
                    'distance'       => $route->distance,
                    'estimated_time' => $route->estimated_time,
                    'status'         => $route->status,
                ],
                'stops'    => $stops,
                'vehicles' => $vehicles,
                'summary'  => [
                    'total_stops'    => $route->stops->count(),
                    'total_vehicles' => $route->vehicles->count(),
                    'total_students' => $totalStudents,
                    'total_capacity' => $totalCapacity,
                    'occupancy_pct'  => $occupancyPct,
                ],
            ],
        ]);
    }

    /**
     * GET /mobile/transport/admin/vehicles
     *
     * Vehicle list with assigned route, driver/conductor, compliance dates
     * (with days_until_*), live location, and active allocation count.
     */
    public function vehicles(Request $request): JsonResponse
    {
        $this->assertTransportAdmin($request);
        $schoolId = app('current_school_id');

        $query = TransportVehicle::where('school_id', $schoolId)
            ->with(['driver.user', 'conductor.user', 'route', 'liveLocation'])
            ->withCount(['studentAllocations as allocations_count' => fn($q) => $q->where('status', 'active')]);

        if ($status = $request->input('status')) {
            if (in_array($status, ['active', 'inactive', 'maintenance'], true)) {
                $query->where('status', $status);
            }
        }
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('vehicle_number', 'like', "%{$search}%")
                  ->orWhere('vehicle_name',   'like', "%{$search}%");
            });
        }

        $vehicles = $query->orderBy('vehicle_number')->get();

        $data = $vehicles->map(fn($v) => array_merge(
            $this->vehiclePayload($v, true),
            ['allocations_count' => (int) ($v->allocations_count ?? 0)]
        ))->values();

        return response()->json([
            'data'    => $data,
            'summary' => [
                'total_vehicles'   => $vehicles->count(),
                'active'           => $vehicles->where('status', 'active')->count(),
                'maintenance'      => $vehicles->where('status', 'maintenance')->count(),
                'inactive'         => $vehicles->where('status', 'inactive')->count(),
            ],
        ]);
    }
}
