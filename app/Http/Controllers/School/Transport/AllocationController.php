<?php

namespace App\Http\Controllers\School\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportStudentAllocation;
use App\Models\TransportRoute;
use App\Models\TransportVehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AllocationController extends Controller
{

    public function index()
    {
        $allocations = TransportStudentAllocation::tenant()
            ->with([
                'student:id,admission_no,first_name,last_name',
                'student.user:id,name',
                'route:id,route_name,route_code',
                'stop:id,stop_name,stop_code,fee',
                'vehicle:id,vehicle_number,vehicle_name',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $routes   = TransportRoute::tenant()->where('status', 'active')->with('stops')->orderBy('route_name')->get();
        $vehicles = TransportVehicle::tenant()->where('status', 'active')->orderBy('vehicle_number')->get(['id', 'vehicle_number', 'vehicle_name', 'route_id']);
        $classes  = \App\Models\CourseClass::where('school_id', app('current_school_id'))->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('School/Transport/Assignments/Index', [
            'allocations' => $allocations,
            'routes'      => $routes,
            'vehicles'    => $vehicles,
            'classes'     => $classes,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'student_ids'  => 'required|array',
            'student_ids.*'=> [Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'route_id'     => ['required', Rule::exists('transport_routes', 'id')->where('school_id', $schoolId)],
            'stop_id'      => ['required', Rule::exists('transport_stops', 'id')->where('school_id', $schoolId)],
            'vehicle_id'   => ['nullable', Rule::exists('transport_vehicles', 'id')->where('school_id', $schoolId)],
            'pickup_type'  => 'required|in:pickup,drop,both',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'status'       => 'in:active,inactive',
        ]);

        // Auto-fill transport fee from the selected stop
        $stop = \App\Models\TransportStop::findOrFail($validated['stop_id']);

        foreach ($validated['student_ids'] as $studentId) {
            TransportStudentAllocation::create([
                'student_id'     => $studentId,
                'route_id'       => $validated['route_id'],
                'stop_id'        => $validated['stop_id'],
                'vehicle_id'     => $validated['vehicle_id'],
                'pickup_type'    => $validated['pickup_type'],
                'start_date'     => $validated['start_date'],
                'end_date'       => $validated['end_date'],
                'status'         => $validated['status'],
                'school_id'      => app('current_school_id'),
                'transport_fee'  => $stop->fee,
                'amount_paid'    => 0,
                'discount'       => 0,
                'fine'           => 0,
                'balance'        => $stop->fee,
                'payment_status' => $stop->fee > 0 ? 'unpaid' : 'paid',
            ]);
        }

        return back()->with('success', 'Students allocated to transport.');
    }

    public function update(Request $request, TransportStudentAllocation $allocation)
    {
        $this->authorizeTenant($allocation);
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'route_id'    => ['required', Rule::exists('transport_routes', 'id')->where('school_id', $schoolId)],
            'stop_id'     => ['required', Rule::exists('transport_stops', 'id')->where('school_id', $schoolId)],
            'vehicle_id'  => ['nullable', Rule::exists('transport_vehicles', 'id')->where('school_id', $schoolId)],
            'pickup_type' => 'required|in:pickup,drop,both',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'status'      => 'in:active,inactive',
        ]);

        // Re-fetch fee only if stop changed
        $stopChanged = (int) $validated['stop_id'] !== (int) $allocation->stop_id;
        if ($stopChanged) {
            $stop = \App\Models\TransportStop::where('id', $validated['stop_id'])
                ->where('school_id', $schoolId)->firstOrFail();
            $validated['transport_fee'] = $stop->fee;
        }

        $allocation->update($validated);

        // Rebalance only if the fee changed or status moved to inactive —
        // receipts stay, but balance/payment_status need to follow the new total.
        if ($stopChanged || $allocation->wasChanged('status')) {
            $allocation->refresh()->recalculateTotals();
        }

        return back()->with('success', 'Allocation updated.');
    }

    public function destroy(TransportStudentAllocation $allocation)
    {
        $this->authorizeTenant($allocation);

        $allocation->delete();

        return back()->with('success', 'Allocation removed. Fee entry cancelled.');
    }

    private function authorizeTenant(TransportStudentAllocation $allocation): void
    {
        abort_unless($allocation->school_id === app('current_school_id'), 403);
    }

    public function studentsByClass(Request $request)
    {
        $schoolId = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $classId = $request->get('class_id');
        $sectionId = $request->get('section_id');

        $query = \App\Models\StudentAcademicHistory::with('student:id,first_name,last_name,admission_no')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where('status', 'current');

        if ($sectionId) {
            $query->where('section_id', $sectionId);
        }

        // Exclude students who already have an active transport allocation?
        // Let's just return all students in the class/section and let the user decide.
        // It might be helpful to exclude existing allocations, but for now we list all.

        $students = $query->get()->filter(fn($h) => $h->student !== null)->map(function($h) {
            return [
                'id' => $h->student_id,
                'name' => trim(($h->student->first_name ?? '') . ' ' . ($h->student->last_name ?? '')),
                'admission_no' => $h->student->admission_no,
            ];
        });

        return response()->json($students);
    }
}
