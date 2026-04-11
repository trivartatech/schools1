<?php

namespace App\Http\Controllers\School\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportVehicle;
use App\Models\TransportRoute;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VehicleController extends Controller
{
    public function index()
    {
        $vehicles = TransportVehicle::tenant()
            ->with([
                'driver:id,user_id',
                'driver.user:id,name,phone',
                'route:id,route_name,route_code',
                'liveLocation',
            ])
            ->orderBy('vehicle_number')
            ->get();

        $routes = TransportRoute::tenant()->active()->orderBy('route_name')->get(['id', 'route_name', 'route_code']);

        // Only staff with Driver designation
        $drivers = Staff::tenant()
            ->whereHas('designation', fn($q) => $q->whereRaw('LOWER(name) = ?', ['driver']))
            ->with('user:id,name,phone')
            ->get(['id', 'user_id']);

        return Inertia::render('School/Transport/Vehicles/Index', [
            'vehicles' => $vehicles,
            'routes'   => $routes,
            'drivers'  => $drivers,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'vehicle_number'   => 'required|string|max:50|unique:transport_vehicles,vehicle_number,NULL,id,school_id,' . $schoolId,
            'vehicle_name'     => 'nullable|string|max:255',
            'driver_id'        => ['nullable', Rule::exists('staff', 'id')->where('school_id', $schoolId)],
            'conductor_name'   => 'nullable|string|max:255',
            'capacity'         => 'required|integer|min:1',
            'route_id'         => ['nullable', Rule::exists('transport_routes', 'id')->where('school_id', $schoolId)],
            'gps_device_id'    => 'nullable|string|max:100|unique:transport_vehicles,gps_device_id',
            'insurance_expiry' => 'nullable|date',
            'fitness_expiry'   => 'nullable|date',
            'pollution_expiry' => 'nullable|date',
            'status'           => 'in:active,inactive,maintenance',
        ]);

        TransportVehicle::create([...$validated, 'school_id' => $schoolId]);

        return back()->with('success', 'Vehicle added successfully.');
    }

    public function update(Request $request, TransportVehicle $vehicle)
    {
        $this->authorizeTenant($vehicle);
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'vehicle_number'   => 'required|string|max:50|unique:transport_vehicles,vehicle_number,' . $vehicle->id . ',id,school_id,' . $schoolId,
            'vehicle_name'     => 'nullable|string|max:255',
            'driver_id'        => ['nullable', Rule::exists('staff', 'id')->where('school_id', $schoolId)],
            'conductor_name'   => 'nullable|string|max:255',
            'capacity'         => 'required|integer|min:1',
            'route_id'         => ['nullable', Rule::exists('transport_routes', 'id')->where('school_id', $schoolId)],
            'gps_device_id'    => 'nullable|string|max:100|unique:transport_vehicles,gps_device_id,' . $vehicle->id,
            'insurance_expiry' => 'nullable|date',
            'fitness_expiry'   => 'nullable|date',
            'pollution_expiry' => 'nullable|date',
            'status'           => 'in:active,inactive,maintenance',
        ]);

        $vehicle->update($validated);
        return back()->with('success', 'Vehicle updated.');
    }

    public function destroy(TransportVehicle $vehicle)
    {
        $this->authorizeTenant($vehicle);
        $vehicle->delete();
        return back()->with('success', 'Vehicle deleted.');
    }

    private function authorizeTenant(TransportVehicle $vehicle)
    {
        abort_unless($vehicle->school_id === app('current_school_id'), 403);
    }
}
