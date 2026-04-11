<?php

namespace App\Http\Controllers\School\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportAttendance;
use App\Models\TransportRoute;
use App\Models\TransportStudentAllocation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;

class TransportAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $date = $request->get('date', Carbon::today()->toDateString());

        $routes = TransportRoute::tenant()
            ->where('status', 'active')
            ->with(['vehicles' => fn($q) => $q->select('id', 'route_id', 'vehicle_number', 'vehicle_name')])
            ->orderBy('route_name')
            ->get(['id', 'route_name', 'route_code']);

        return Inertia::render('School/Transport/Attendance/Index', [
            'routes' => $routes,
            'date'   => $date,
        ]);
    }

    /**
     * Get students for a route with their attendance status for a given date.
     */
    public function students(Request $request)
    {
        $schoolId = app('current_school_id');
        $routeId = $request->get('route_id');
        $date = $request->get('date', Carbon::today()->toDateString());
        $tripType = $request->get('trip_type', 'pickup');

        $allocations = TransportStudentAllocation::where('school_id', $schoolId)
            ->where('route_id', $routeId)
            ->where('status', 'active')
            ->whereIn('pickup_type', [$tripType, 'both'])
            ->with([
                'student:id,admission_no',
                'student.user:id,name',
                'stop:id,stop_name,stop_order',
            ])
            ->get();

        // Existing attendance records for this date/trip
        $existing = TransportAttendance::where('school_id', $schoolId)
            ->where('route_id', $routeId)
            ->where('date', $date)
            ->where('trip_type', $tripType)
            ->get()
            ->keyBy('student_id');

        $students = $allocations->map(function ($alloc) use ($existing) {
            $att = $existing->get($alloc->student_id);
            return [
                'student_id'   => $alloc->student_id,
                'name'         => $alloc->student->user->name ?? '—',
                'admission_no' => $alloc->student->admission_no,
                'stop_name'    => $alloc->stop->stop_name ?? '—',
                'stop_order'   => $alloc->stop->stop_order ?? 0,
                'vehicle_id'   => $alloc->vehicle_id,
                'status'       => $att?->status ?? null,
                'boarded_at'   => $att?->boarded_at,
                'notes'        => $att?->notes,
            ];
        })->sortBy('stop_order')->values();

        return response()->json($students);
    }

    /**
     * Bulk save attendance for a route/date/trip.
     */
    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'route_id'  => ['required', Rule::exists('transport_routes', 'id')->where('school_id', $schoolId)],
            'date'      => 'required|date',
            'trip_type' => 'required|in:pickup,drop',
            'records'   => 'required|array',
            'records.*.student_id' => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'records.*.status'     => 'required|in:present,absent,late',
            'records.*.boarded_at' => 'nullable|date_format:H:i',
            'records.*.notes'      => 'nullable|string|max:255',
        ]);

        $route = TransportRoute::where('id', $validated['route_id'])
            ->where('school_id', $schoolId)->firstOrFail();

        // Get vehicle for this route
        $vehicle = $route->vehicles()->first();

        foreach ($validated['records'] as $record) {
            TransportAttendance::updateOrCreate(
                [
                    'school_id'  => $schoolId,
                    'student_id' => $record['student_id'],
                    'date'       => $validated['date'],
                    'trip_type'  => $validated['trip_type'],
                ],
                [
                    'route_id'   => $validated['route_id'],
                    'vehicle_id' => $vehicle?->id,
                    'status'     => $record['status'],
                    'boarded_at' => $record['boarded_at'] ?? null,
                    'notes'      => $record['notes'] ?? null,
                    'marked_by'  => auth()->id(),
                ]
            );
        }

        return back()->with('success', 'Attendance saved for ' . count($validated['records']) . ' students.');
    }

    /**
     * Attendance report for a date range.
     */
    public function report(Request $request)
    {
        $schoolId = app('current_school_id');
        $from = $request->get('from', Carbon::today()->subDays(7)->toDateString());
        $to = $request->get('to', Carbon::today()->toDateString());
        $routeId = $request->get('route_id');

        $query = TransportAttendance::tenant()
            ->whereBetween('date', [$from, $to])
            ->with([
                'student:id,admission_no',
                'student.user:id,name',
                'route:id,route_name',
            ]);

        if ($routeId) {
            $query->where('route_id', $routeId);
        }

        $records = $query->orderBy('date', 'desc')->orderBy('trip_type')->get();

        // Summary stats
        $summary = [
            'total_records' => $records->count(),
            'present'       => $records->where('status', 'present')->count(),
            'absent'        => $records->where('status', 'absent')->count(),
            'late'          => $records->where('status', 'late')->count(),
        ];

        $routes = TransportRoute::tenant()->where('status', 'active')
            ->get(['id', 'route_name', 'route_code']);

        return Inertia::render('School/Transport/Attendance/Report', [
            'records' => $records,
            'summary' => $summary,
            'routes'  => $routes,
            'filters' => ['from' => $from, 'to' => $to, 'route_id' => $routeId],
        ]);
    }
}
