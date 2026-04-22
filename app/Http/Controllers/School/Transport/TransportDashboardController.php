<?php

namespace App\Http\Controllers\School\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportRoute;
use App\Models\TransportVehicle;
use App\Models\TransportStudentAllocation;
use App\Models\TransportStop;
use App\Models\TransportAttendance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class TransportDashboardController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');
        $today = Carbon::today();

        $routes   = TransportRoute::tenant()->count();
        $activeRoutes = TransportRoute::tenant()->where('status', 'active')->count();
        $vehicles = TransportVehicle::tenant()->count();
        $activeVehicles = TransportVehicle::tenant()->where('status', 'active')->count();
        $totalStudents = TransportStudentAllocation::tenant()->where('status', 'active')->count();
        $totalStops = TransportStop::tenant()->count();

        // Vehicle document expiry alerts (next 30 days)
        $expiryThreshold = $today->copy()->addDays(30);
        $expiringVehicles = TransportVehicle::tenant()
            ->where('status', '!=', 'inactive')
            ->where(function ($q) use ($today, $expiryThreshold) {
                $q->whereBetween('insurance_expiry', [$today, $expiryThreshold])
                  ->orWhereBetween('fitness_expiry', [$today, $expiryThreshold])
                  ->orWhereBetween('pollution_expiry', [$today, $expiryThreshold])
                  ->orWhere('insurance_expiry', '<', $today)
                  ->orWhere('fitness_expiry', '<', $today)
                  ->orWhere('pollution_expiry', '<', $today);
            })
            ->with(['driver:id,user_id', 'driver.user:id,name', 'route:id,route_name'])
            ->get(['id', 'vehicle_number', 'vehicle_name', 'driver_id', 'route_id',
                    'insurance_expiry', 'fitness_expiry', 'pollution_expiry', 'status']);

        // Route utilization (students per route vs capacity)
        $routeUtilization = TransportRoute::tenant()
            ->where('status', 'active')
            ->withCount(['studentAllocations as active_students' => fn($q) => $q->where('status', 'active')])
            ->with(['vehicles' => fn($q) => $q->select('id', 'route_id', 'capacity', 'vehicle_number')])
            ->get(['id', 'route_name', 'route_code'])
            ->map(function ($route) {
                $totalCapacity = $route->vehicles->sum('capacity');
                return [
                    'id'              => $route->id,
                    'route_name'      => $route->route_name,
                    'route_code'      => $route->route_code,
                    'active_students' => $route->active_students,
                    'total_capacity'  => $totalCapacity,
                    'utilization_pct' => $totalCapacity > 0 ? round(($route->active_students / $totalCapacity) * 100) : 0,
                    'vehicle_count'   => $route->vehicles->count(),
                ];
            });

        // Today's attendance summary
        $todayAttendance = TransportAttendance::tenant()
            ->where('date', $today)
            ->selectRaw("trip_type, status, count(*) as cnt")
            ->groupBy('trip_type', 'status')
            ->get();

        return Inertia::render('School/Transport/Dashboard', [
            'stats' => [
                'total_routes'     => $routes,
                'active_routes'    => $activeRoutes,
                'total_vehicles'   => $vehicles,
                'active_vehicles'  => $activeVehicles,
                'total_students'   => $totalStudents,
                'total_stops'      => $totalStops,
            ],
            'expiringVehicles'  => $expiringVehicles,
            'routeUtilization'  => $routeUtilization,
            'todayAttendance'   => $todayAttendance,
        ]);
    }

    /**
     * Route optimization report.
     */
    public function routeReport()
    {
        $schoolId = app('current_school_id');

        $routes = TransportRoute::tenant()
            ->with(['stops' => fn($q) => $q->withCount(['studentAllocations as student_count' => fn($sq) => $sq->where('status', 'active')])->orderBy('stop_order')])
            ->withCount(['studentAllocations as total_students' => fn($q) => $q->where('status', 'active')])
            ->with(['vehicles' => fn($q) => $q->select('id', 'route_id', 'vehicle_number', 'vehicle_name', 'capacity', 'status')])
            ->orderBy('route_name')
            ->get();

        $routes->each(function ($route) {
            $totalCapacity = $route->vehicles->sum('capacity');
            $route->total_capacity = $totalCapacity;
            $route->utilization_pct = $totalCapacity > 0 ? round(($route->total_students / $totalCapacity) * 100) : 0;
            $route->total_fee_revenue = TransportStudentAllocation::where('route_id', $route->id)
                ->where('school_id', app('current_school_id'))
                ->where('status', 'active')
                ->sum('transport_fee');
        });

        return Inertia::render('School/Transport/Reports/RouteReport', [
            'routes' => $routes,
        ]);
    }

    /**
     * Fee defaulters report.
     */
    public function feeDefaulters()
    {
        $defaulters = TransportStudentAllocation::tenant()
            ->where('status', 'active')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('balance', '>', 0)
            ->with([
                'student:id,admission_no',
                'student.user:id,name',
                'route:id,route_name,route_code',
                'stop:id,stop_name',
            ])
            ->orderByDesc('balance')
            ->get()
            ->map(fn ($alloc) => [
                'allocation' => $alloc,
                'total_due'  => (float) $alloc->balance,
            ])
            ->values();

        return Inertia::render('School/Transport/Reports/FeeDefaulters', [
            'defaulters' => $defaulters,
        ]);
    }

    /**
     * Parent transport view — child's allocation, route, vehicle, driver info.
     */
    public function parentView(Request $request)
    {
        $user = auth()->user();
        $schoolId = app('current_school_id');

        // Get parent's children student IDs
        $parent = \App\Models\StudentParent::where('user_id', $user->id)->first();
        $studentIds = $parent ? $parent->students()->pluck('students.id') : collect();

        $allocations = TransportStudentAllocation::where('school_id', $schoolId)
            ->whereIn('student_id', $studentIds)
            ->where('status', 'active')
            ->with([
                'student:id,admission_no',
                'student.user:id,name',
                'route:id,route_name,route_code,start_location,end_location',
                'route.stops' => fn($q) => $q->orderBy('stop_order'),
                'stop:id,stop_name,pickup_time,drop_time,fee',
                'vehicle:id,vehicle_number,vehicle_name,driver_id',
                'vehicle.driver:id,user_id',
                'vehicle.driver.user:id,name,phone',
                'vehicle.liveLocation',
            ])
            ->get();

        return Inertia::render('School/Transport/ParentView', [
            'allocations' => $allocations,
        ]);
    }
}
