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
