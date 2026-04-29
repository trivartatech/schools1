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
        $schoolId = app('current_school_id');

        $allocations = TransportStudentAllocation::tenant()
            ->with([
                'student:id,admission_no,first_name,last_name',
                'student.user:id,name',
                'student.currentAcademicHistory:id,student_id,class_id,section_id',
                'student.currentAcademicHistory.courseClass:id,name',
                'student.currentAcademicHistory.section:id,name',
                'route:id,route_name,route_code',
                'stop:id,stop_name,stop_code,fee',
                'vehicle:id,vehicle_number,vehicle_name',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $routes   = TransportRoute::tenant()->where('status', 'active')->with('stops')->orderBy('route_name')->get();
        $vehicles = TransportVehicle::tenant()->where('status', 'active')->orderBy('vehicle_number')->get(['id', 'vehicle_number', 'vehicle_name', 'route_id']);
        $classes  = \App\Models\CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get(['id', 'name']);

        $school          = \App\Models\School::find($schoolId);
        $standardMonths  = (float) ($school?->settings['transport_standard_months'] ?? 10);
        if ($standardMonths <= 0) $standardMonths = 10.0;

        return Inertia::render('School/Transport/Assignments/Index', [
            'allocations'    => $allocations,
            'routes'         => $routes,
            'vehicles'       => $vehicles,
            'classes'        => $classes,
            'standardMonths' => $standardMonths,
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
            'months'       => 'required|integer|min:0|max:24',
            'days'         => 'nullable|integer|min:0|max:30',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'status'       => 'in:active,inactive',
        ]);

        $monthsOpted = $this->composeMonthsOpted($validated['months'], $validated['days'] ?? 0);
        abort_if($monthsOpted < 0.5, 422, 'Minimum term is 15 days.');

        $stop     = \App\Models\TransportStop::findOrFail($validated['stop_id']);
        $fee      = $this->computeFee((float) $stop->fee, $monthsOpted, $schoolId);

        foreach ($validated['student_ids'] as $studentId) {
            TransportStudentAllocation::create([
                'student_id'     => $studentId,
                'route_id'       => $validated['route_id'],
                'stop_id'        => $validated['stop_id'],
                'vehicle_id'     => $validated['vehicle_id'],
                'pickup_type'    => $validated['pickup_type'],
                'start_date'     => $validated['start_date'] ?? null,
                'end_date'       => $validated['end_date']   ?? null,
                'status'         => $validated['status']     ?? 'active',
                'school_id'      => $schoolId,
                'transport_fee'  => $fee,
                'months_opted'   => $monthsOpted,
                'amount_paid'    => 0,
                'discount'       => 0,
                'fine'           => 0,
                'balance'        => $fee,
                'payment_status' => $fee > 0 ? 'unpaid' : 'paid',
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
            'months'      => 'required|integer|min:0|max:24',
            'days'        => 'nullable|integer|min:0|max:30',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'status'      => 'in:active,inactive',
        ]);

        $monthsOpted = $this->composeMonthsOpted($validated['months'], $validated['days'] ?? 0);
        abort_if($monthsOpted < 0.5, 422, 'Minimum term is 15 days.');

        $stop = \App\Models\TransportStop::where('id', $validated['stop_id'])
            ->where('school_id', $schoolId)->firstOrFail();
        $fee  = $this->computeFee((float) $stop->fee, $monthsOpted, $schoolId);

        $feeChanged = (float) $allocation->transport_fee !== $fee
                   || (float) $allocation->months_opted  !== $monthsOpted;

        $allocation->update([
            'route_id'       => $validated['route_id'],
            'stop_id'        => $validated['stop_id'],
            'vehicle_id'     => $validated['vehicle_id'],
            'pickup_type'    => $validated['pickup_type'],
            'start_date'     => $validated['start_date'] ?? null,
            'end_date'       => $validated['end_date']   ?? null,
            'status'         => $validated['status']     ?? $allocation->status,
            'transport_fee'  => $fee,
            'months_opted'   => $monthsOpted,
        ]);

        if ($feeChanged || $allocation->wasChanged('status')) {
            $allocation->refresh()->recalculateTotals();
        }

        return back()->with('success', 'Allocation updated.');
    }

    /**
     * Combine whole months + extra days into decimal months (30-day month).
     * "5 months 15 days" → 5.50.
     */
    private function composeMonthsOpted(int $months, int $days): float
    {
        return round($months + ($days / 30), 2);
    }

    /**
     * Compute pro-rata transport fee from stop's full-term fee:
     *   fee = round(stop.fee / standard_months * months_opted, 2)
     *
     * standard_months comes from the school's settings (default 10).
     */
    private function computeFee(float $stopFee, float $monthsOpted, int $schoolId): float
    {
        $school   = \App\Models\School::find($schoolId);
        $standard = (float) ($school?->settings['transport_standard_months'] ?? 10);
        $standard = $standard > 0 ? $standard : 10.0;

        return round(($stopFee / $standard) * $monthsOpted, 2);
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
