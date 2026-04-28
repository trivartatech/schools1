<?php

namespace App\Http\Controllers\Api\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportGpsLog;
use App\Models\TransportVehicle;
use App\Models\TransportVehicleLiveLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverTrackingController extends Controller
{
    /**
     * Assigned vehicles for the logged-in driver/conductor.
     * Used by the mobile Route screen (vehicle picker), Dashboard, and
     * Students screen — so the response includes the route + stops with
     * student counts even when GPS isn't running. The Dashboard previously
     * relied on /mobile/transport/live, which only returns vehicles with
     * a recent live_location row — so a freshly-assigned driver who hadn't
     * started tracking yet always saw "no vehicles assigned".
     */
    public function assignedVehicles(Request $request): JsonResponse
    {
        $schoolId = app('current_school_id');
        $user     = $request->user();

        $staffId = \App\Models\Staff::where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->value('id');

        if (!$staffId) {
            return response()->json(['vehicles' => [], 'data' => []]);
        }

        $vehicles = TransportVehicle::where('school_id', $schoolId)
            ->where('status', 'active')
            ->where(function ($q) use ($staffId) {
                $q->where('driver_id', $staffId)
                  ->orWhere('conductor_id', $staffId);
            })
            ->with([
                'route:id,route_name,route_code,start_location,end_location',
                'route.stops' => fn($q) => $q->orderBy('stop_order'),
                'route.stops.studentAllocations' => fn($q) => $q->where('status', 'active'),
                'liveLocation',
            ])
            ->orderBy('vehicle_number')
            ->get()
            ->map(function ($v) {
                $stops = ($v->route?->stops ?? collect())->map(fn ($s) => [
                    'id'            => $s->id,
                    'name'          => $s->stop_name,
                    'stop_order'    => $s->stop_order,
                    'pickup_time'   => $s->pickup_time,
                    'drop_time'     => $s->drop_time,
                    'student_count' => $s->studentAllocations->count(),
                    // status is inferred client-side once GPS is live; default upcoming
                    'status'        => 'upcoming',
                ])->values();

                $live   = $v->liveLocation;
                $isLive = $live && $live->updated_at >= now()->subMinutes(5);

                return [
                    'id'             => $v->id,
                    'vehicle_number' => $v->vehicle_number,
                    'vehicle_name'   => $v->vehicle_name,
                    'route_id'       => $v->route_id,
                    'route_name'     => $v->route?->route_name,
                    'route_code'     => $v->route?->route_code,
                    'capacity'       => $v->capacity,
                    'status'         => $v->status,
                    'is_live'        => $isLive,
                    'stops'          => $stops,
                    'total_students' => $stops->sum('student_count'),
                ];
            })
            ->values();

        // Returned twice under different keys for mobile-client compatibility:
        // older code reads `data`, newer code reads `vehicles`.
        return response()->json(['vehicles' => $vehicles, 'data' => $vehicles]);
    }

    /**
     * List all active vehicles with their live status.
     */
    public function status(Request $request): JsonResponse
    {
        $schoolId = app('current_school_id');
        $user = $request->user();

        $query = TransportVehicle::where('school_id', $schoolId)
            ->where('status', 'active');

        // Drivers only see their own assigned vehicle
        if ($user->user_type === 'driver') {
            $staffId = \App\Models\Staff::where('user_id', $user->id)
                ->where('school_id', $schoolId)
                ->value('id');
            $query->where('driver_id', $staffId);
        }

        $vehicles = $query->with([
                'route:id,route_name,route_code',
                'driver:id,user_id',
                'driver.user:id,name',
                'liveLocation',
            ])
            ->orderBy('vehicle_number')
            ->get()
            ->map(function ($v) {
                $live = $v->liveLocation;
                $isLive = $live && $live->updated_at >= now()->subMinutes(5);

                return [
                    'id'             => $v->id,
                    'vehicle_number' => $v->vehicle_number,
                    'vehicle_name'   => $v->vehicle_name,
                    'route_name'     => $v->route?->route_name,
                    'route_code'     => $v->route?->route_code,
                    'capacity'       => $v->capacity,
                    'driver_name'    => $v->driver?->user?->name ?? 'Unassigned',
                    'is_live'        => $isLive,
                    'last_location'  => $isLive ? [
                        'latitude'   => $live->latitude,
                        'longitude'  => $live->longitude,
                        'speed'      => $live->speed,
                        'updated_at' => $live->updated_at->toDateTimeString(),
                    ] : null,
                ];
            });

        return response()->json(['vehicles' => $vehicles]);
    }

    /**
     * Receive a GPS update from the driver's browser/phone.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|integer',
            'latitude'   => 'required|numeric|between:-90,90',
            'longitude'  => 'required|numeric|between:-180,180',
            'speed'      => 'nullable|numeric|min:0',
            'heading'    => 'nullable|numeric|between:0,360',
        ]);

        $user = $request->user();
        $schoolId = app('current_school_id');

        $vehicleQuery = TransportVehicle::where('id', $validated['vehicle_id'])
            ->where('school_id', $schoolId);

        // Drivers can only update their own assigned vehicle
        if ($user->user_type === 'driver') {
            $staffId = \App\Models\Staff::where('user_id', $user->id)
                ->where('school_id', $schoolId)
                ->value('id');
            $vehicleQuery->where('driver_id', $staffId);
        }

        $vehicle = $vehicleQuery->first();

        if (! $vehicle) {
            return response()->json(['error' => 'Vehicle not found or not assigned to you.'], 404);
        }

        TransportGpsLog::create([
            'vehicle_id' => $vehicle->id,
            'latitude'   => $validated['latitude'],
            'longitude'  => $validated['longitude'],
            'speed'      => $validated['speed'] ?? 0,
            'heading'    => $validated['heading'] ?? null,
            'timestamp'  => now(),
            'created_at' => now(),
        ]);

        TransportVehicleLiveLocation::updateOrCreate(
            ['vehicle_id' => $vehicle->id],
            [
                'latitude'   => $validated['latitude'],
                'longitude'  => $validated['longitude'],
                'speed'      => $validated['speed'] ?? 0,
                'heading'    => $validated['heading'] ?? null,
                'updated_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'vehicle_id' => $vehicle->id]);
    }

    /**
     * Stop tracking — mark the live location as stale.
     */
    public function stop(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|integer',
        ]);

        $vehicle = TransportVehicle::where('id', $validated['vehicle_id'])
            ->where('school_id', app('current_school_id'))
            ->first();

        if (! $vehicle) {
            return response()->json(['error' => 'Vehicle not found.'], 404);
        }

        TransportVehicleLiveLocation::where('vehicle_id', $vehicle->id)
            ->update(['updated_at' => now()->subMinutes(10)]);

        return response()->json(['success' => true, 'message' => 'Tracking stopped.']);
    }
}
