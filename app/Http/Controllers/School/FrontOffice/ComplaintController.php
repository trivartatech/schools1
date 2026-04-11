<?php

namespace App\Http\Controllers\School\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ComplaintController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');

        $query = Complaint::where('school_id', $schoolId)
                    ->with(['raisedBy', 'assignedDepartment', 'assignedTo']);

        if ($yearId = session('selected_academic_year_id')) {
            $query->where('academic_year_id', $yearId);
        }

        return Inertia::render('School/FrontOffice/Complaints/Index', [
            'complaints' => $query->latest()->get(),
            'departments' => Department::where('school_id', $schoolId)->get()
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'type' => 'required|in:Academic,Transport,Hostel,Facility,Other',
            'raised_by_type' => 'nullable|string',
            'raised_by_id' => 'nullable|integer',
            'description' => 'required|string',
            'priority' => 'required|in:Low,Medium,High,Critical',
            'assigned_department_id' => ['nullable', Rule::exists('departments', 'id')->where('school_id', $schoolId)],
            'sla_hours' => 'nullable|integer|min:1|max:720',
        ]);

        // Default SLA hours by priority
        if (empty($validated['sla_hours'])) {
            $validated['sla_hours'] = match ($validated['priority']) {
                'Critical' => 4,
                'High'     => 12,
                'Medium'   => 24,
                'Low'      => 72,
            };
        }

        $complaint = new Complaint($validated);
        $complaint->school_id = $schoolId;
        $complaint->academic_year_id = session('selected_academic_year_id');
        $complaint->status = 'Open';

        $complaint->save();

        return back()->with('success', 'Complaint logged successfully.');
    }

    public function update(Request $request, Complaint $complaint)
    {
        $schoolId = app('current_school_id');
        abort_if($complaint->school_id !== $schoolId, 403);

        $validated = $request->validate([
            'status' => 'required|in:Open,In Progress,Resolved,Closed',
            'resolution_notes' => 'nullable|string',
            'assigned_department_id' => ['nullable', Rule::exists('departments', 'id')->where('school_id', $schoolId)],
        ]);

        if ($validated['status'] === 'Resolved' || $validated['status'] === 'Closed') {
            if (!$complaint->resolved_at) {
                $complaint->resolved_at = now();
            }
        } else {
            $complaint->resolved_at = null;
        }

        $complaint->update($validated);

        return back()->with('success', 'Complaint updated successfully.');
    }

    public function destroy(Complaint $complaint)
    {
        abort_if($complaint->school_id !== app('current_school_id'), 403);

        $complaint->delete();
        return back()->with('success', 'Complaint removed.');
    }
}
