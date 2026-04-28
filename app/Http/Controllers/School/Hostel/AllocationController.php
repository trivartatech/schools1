<?php

namespace App\Http\Controllers\School\Hostel;

use App\Http\Controllers\Controller;
use App\Models\HostelRoom;
use App\Models\HostelBed;
use App\Models\HostelStudent;
use App\Models\Student;
use App\Services\HostelFeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AllocationController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $query = HostelStudent::where('school_id', $schoolId)
                    ->with(['student', 'bed.room.hostel']);

        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'Active');
        }

        $allocations = $query->paginate(20);

        $availableBeds = HostelBed::where('school_id', $schoolId)
                            ->where('status', 'Available')
                            ->with('room.hostel')
                            ->get();

        $students = Student::where('school_id', $schoolId)
            ->with('studentParent:id,guardian_name,father_name,mother_name,primary_phone,father_phone')
            ->get(['id', 'first_name', 'last_name', 'admission_no', 'parent_id']);

        return Inertia::render('School/Hostel/Allocations/Index', [
            'allocations' => $allocations,
            'availableBeds' => $availableBeds,
            'students' => $students,
            'filters' => $request->only('status')
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'hostel_bed_id' => ['required', Rule::exists('hostel_beds', 'id')->where('school_id', $schoolId)],
            'admission_date' => 'required|date',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_phone' => 'nullable|string|max:255',
            'guardian_relation' => 'nullable|string|max:255',
            'medical_info' => 'nullable|string',
            'mess_type' => 'required|in:Veg,Non-Veg,Custom,None',
            'months_opted' => 'nullable|numeric|min:0',
        ]);

        $bed = HostelBed::where('id', $validated['hostel_bed_id'])
                        ->where('school_id', $schoolId)
                        ->firstOrFail();

        if ($bed->status !== 'Available') {
            return back()->with('error', 'Selected bed is not available.');
        }

        // Check student isn't already allocated to a bed
        $existing = HostelStudent::where('school_id', $schoolId)
            ->where('student_id', $validated['student_id'])
            ->where('status', 'Active')
            ->exists();

        if ($existing) {
            return back()->with('error', 'This student is already allocated to a hostel bed.');
        }

        $monthsOpted = $validated['months_opted'] ?? null;
        unset($validated['months_opted']);

        $validated['school_id'] = $schoolId;
        $validated['status'] = 'Active';

        DB::transaction(function () use ($validated, $bed, $monthsOpted) {
            $allocation = HostelStudent::create($validated);
            $bed->update(['status' => 'Occupied']);
            app(HostelFeeService::class)->seedAllocationFee($allocation, $monthsOpted);
        });

        return back()->with('success', 'Student assigned to bed successfully');
    }

    public function update(Request $request, HostelStudent $allocation)
    {
        abort_if($allocation->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'guardian_name' => 'nullable|string|max:255',
            'guardian_phone' => 'nullable|string|max:255',
            'guardian_relation' => 'nullable|string|max:255',
            'medical_info' => 'nullable|string',
            'mess_type' => 'required|in:Veg,Non-Veg,Custom,None',
        ]);

        $allocation->update($validated);
        return back()->with('success', 'Allocation details updated');
    }

    public function vacate(Request $request, HostelStudent $allocation)
    {
        abort_if($allocation->school_id !== app('current_school_id'), 403);
        abort_if($allocation->status !== 'Active', 422, 'Only active allocations can be vacated.');

        $validated = $request->validate([
            'vacate_date' => 'required|date'
        ]);

        DB::transaction(function () use ($allocation, $validated) {
            $allocation->update([
                'status'      => 'Vacated',
                'vacate_date' => $validated['vacate_date']
            ]);

            if ($allocation->bed) {
                $allocation->bed->update(['status' => 'Available']);
            }

            $allocation->refresh()->recalculateTotals();
        });

        return back()->with('success', 'Student vacated from bed successfully');
    }

    /**
     * Transfer student to a different bed without vacate/re-allocate.
     */
    public function transfer(Request $request, HostelStudent $allocation)
    {
        abort_if($allocation->school_id !== app('current_school_id'), 403);
        abort_if($allocation->status !== 'Active', 422, 'Only active allocations can be transferred.');

        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'hostel_bed_id' => ['required', Rule::exists('hostel_beds', 'id')->where('school_id', $schoolId)],
            'reason'        => 'nullable|string|max:500',
        ]);

        $newBed = \App\Models\HostelBed::where('id', $validated['hostel_bed_id'])
            ->where('school_id', $schoolId)
            ->firstOrFail();

        if ($newBed->status !== 'Available') {
            return back()->with('error', 'Target bed is not available.');
        }

        if ($newBed->id === $allocation->hostel_bed_id) {
            return back()->with('error', 'Student is already in this bed.');
        }

        DB::transaction(function () use ($allocation, $newBed) {
            // Free old bed
            if ($allocation->bed) {
                $allocation->bed->update(['status' => 'Available']);
            }

            // Assign new bed
            $allocation->update(['hostel_bed_id' => $newBed->id]);
            $newBed->update(['status' => 'Occupied']);

            // Re-seed hostel_fee using the new room's monthly cost; keep
            // months_opted as-is. recalculateTotals() reconciles balance
            // against existing receipts.
            app(HostelFeeService::class)
                ->seedAllocationFee($allocation->refresh(), (float) $allocation->months_opted);
            $allocation->refresh()->recalculateTotals();
        });

        return back()->with('success', 'Student transferred to new bed successfully.');
    }
}
