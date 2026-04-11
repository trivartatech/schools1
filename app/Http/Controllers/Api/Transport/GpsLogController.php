<?php

namespace App\Http\Controllers\Api\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportGpsLog;
use App\Models\TransportVehicle;
use App\Models\TransportVehicleLiveLocation;
use Illuminate\Http\Request;

class GpsLogController extends Controller
{
    /**
     * Receive a GPS update from a physical GPS device.
     *
     * POST /api/gps/update
     * Payload:
     *  {
     *    "device_id": "BUS001",
     *    "latitude":   12.9716,
     *    "longitude":  77.5946,
     *    "speed":      35,
     *    "heading":    120,
     *    "timestamp":  "2026-03-11 08:20:00"
     *  }
     */
    public function update(Request $request)
    {
        // Enforce basic API Key authentication for physical devices
        $apiKey = config('services.gps.api_key', env('GPS_API_KEY'));
        if ($apiKey && $request->header('X-GPS-KEY') !== $apiKey && $request->input('api_key') !== $apiKey) {
            return response()->json(['error' => 'Unauthorized GPS device.'], 401);
        }

        $validated = $request->validate([
            'device_id' => 'required|string',
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed'     => 'nullable|numeric|min:0',
            'heading'   => 'nullable|numeric|between:0,359',
            'timestamp' => 'nullable|date',
        ]);

        // Resolve vehicle by GPS device ID
        $vehicle = TransportVehicle::where('gps_device_id', $validated['device_id'])->first();

        if (! $vehicle) {
            return response()->json(['error' => 'Device not registered.'], 404);
        }

        $ts = $validated['timestamp'] ?? now();

        // 1. Insert full GPS log (history / analytics)
        TransportGpsLog::create([
            'vehicle_id' => $vehicle->id,
            'latitude'   => $validated['latitude'],
            'longitude'  => $validated['longitude'],
            'speed'      => $validated['speed'] ?? 0,
            'heading'    => $validated['heading'] ?? null,
            'timestamp'  => $ts,
            'created_at' => now(),
        ]);

        // 2. Upsert live location (single row per vehicle, fast map queries)
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
     * Return all live vehicle locations for the map dashboard.
     * Authenticated school users only.
     *
     * GET /api/transport/live
     */
    public function live(Request $request)
    {
        $schoolId = app('current_school_id');

        $locations = TransportVehicleLiveLocation::with([
            'vehicle:id,school_id,vehicle_number,vehicle_name,route_id,driver_id,conductor_name,capacity,status',
            'vehicle.route:id,route_name,route_code,start_location,end_location,distance,estimated_time',
            'vehicle.route.stops' => fn($q) => $q->orderBy('stop_order'),
            'vehicle.route.stops.studentAllocations' => fn($q) => $q->where('status', 'active'),
            'vehicle.driver:id,user_id',
            'vehicle.driver.user:id,name,phone',
        ])
            ->whereHas('vehicle', fn($q) => $q->where('school_id', $schoolId))
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->get();

        // Enrich each location with computed tracking data
        $locations->transform(function ($loc) {
            $vehicle = $loc->vehicle;
            $route   = $vehicle?->route;
            $stops   = $route?->stops ?? collect();

            // Determine nearest stop and which stops the bus has passed
            $busLat = (float) $loc->latitude;
            $busLng = (float) $loc->longitude;
            $speed  = (float) ($loc->speed ?? 0);

            $nearestIdx  = 0;
            $nearestDist = PHP_FLOAT_MAX;

            $stopsData = $stops->values()->map(function ($stop, $idx) use ($busLat, $busLng, &$nearestIdx, &$nearestDist) {
                if ($stop->latitude !== null && $stop->longitude !== null) {
                    $dist = $this->haversine($busLat, $busLng, (float) $stop->latitude, (float) $stop->longitude);
                    if ($dist < $nearestDist) {
                        $nearestDist = $dist;
                        $nearestIdx  = $idx;
                    }
                }
                return [
                    'id'           => $stop->id,
                    'name'         => $stop->stop_name,
                    'stop_order'   => $stop->stop_order,
                    'pickup_time'  => $stop->pickup_time,
                    'drop_time'    => $stop->drop_time,
                    'latitude'     => $stop->latitude,
                    'longitude'    => $stop->longitude,
                    'student_count'=> $stop->studentAllocations->count(),
                    'distance_from_school' => $stop->distance_from_school,
                ];
            });

            // Mark stops as passed / current / upcoming
            $stopsData = $stopsData->map(function ($s, $idx) use ($nearestIdx) {
                $s['status'] = $idx < $nearestIdx ? 'passed' : ($idx === $nearestIdx ? 'current' : 'upcoming');
                return $s;
            });

            // Next stop is the one after the nearest
            $nextStop = $stopsData->get($nearestIdx + 1);
            $nextStopDist = ($nextStop && $nextStop['latitude'] !== null && $nextStop['longitude'] !== null)
                ? $this->haversine($busLat, $busLng, (float) $nextStop['latitude'], (float) $nextStop['longitude'])
                : null;

            // ETA in minutes (distance / speed * 60), fallback 0
            $etaMinutes = ($speed > 0 && $nextStopDist !== null)
                ? round(($nextStopDist / $speed) * 60)
                : null;

            // Distance from bus to last stop (school)
            $lastStop = $stopsData->last();
            $distToSchool = ($lastStop && $lastStop['latitude'] !== null && $lastStop['longitude'] !== null)
                ? round($this->haversine($busLat, $busLng, (float) $lastStop['latitude'], (float) $lastStop['longitude']), 1)
                : null;

            $loc->tracking = [
                'stops'           => $stopsData->values(),
                'nearest_stop'    => $stopsData->get($nearestIdx),
                'next_stop'       => $nextStop,
                'next_stop_dist'  => $nextStopDist ? round($nextStopDist, 1) : null,
                'eta_minutes'     => $etaMinutes,
                'dist_to_school'  => $distToSchool,
                'driver_name'     => $vehicle?->driver?->user?->name ?? 'N/A',
                'driver_phone'    => $vehicle?->driver?->user?->phone ?? null,
                'conductor_name'  => $vehicle?->conductor_name ?? 'N/A',
                'total_students'  => $stopsData->sum('student_count'),
            ];

            return $loc;
        });

        return response()->json($locations);
    }

    /**
     * Haversine distance between two points in km.
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371; // Earth radius in km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }
}
