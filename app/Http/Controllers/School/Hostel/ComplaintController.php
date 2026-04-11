<?php

namespace App\Http\Controllers\School\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\HostelComplaint;
use App\Models\HostelStudent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $query = HostelComplaint::where('school_id', $schoolId)
            ->with(['hostel', 'student', 'reporter', 'assignee']);

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->hostel_id) {
            $query->where('hostel_id', $request->hostel_id);
        }
        if ($request->priority) {
            $query->where('priority', $request->priority);
        }

        $complaints = $query->latest()->paginate(20);

        $hostels = Hostel::where('school_id', $schoolId)->get(['id', 'name']);

        $activeStudentIds = HostelStudent::where('school_id', $schoolId)
            ->where('status', 'Active')->pluck('student_id');
        $students = Student::whereIn('id', $activeStudentIds)
            ->get(['id', 'first_name', 'last_name', 'admission_no']);

        $staff = User::where('school_id', $schoolId)
            ->whereNotIn('user_type', ['student', 'parent'])
            ->get(['id', 'name']);

        return Inertia::render('School/Hostel/Complaints/Index', [
            'complaints' => $complaints,
            'hostels'    => $hostels,
            'students'   => $students,
            'staff'      => $staff,
            'filters'    => $request->only('status', 'hostel_id', 'priority'),
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'hostel_id'   => ['required', Rule::exists('hostels', 'id')->where('school_id', $schoolId)],
            'student_id'  => ['nullable', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'category'    => 'required|in:maintenance,electrical,plumbing,furniture,cleanliness,pest_control,other',
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'location'    => 'nullable|string|max:255',
            'priority'    => 'required|in:low,medium,high,urgent',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('school_id', $schoolId)],
        ]);

        $validated['school_id']   = $schoolId;
        $validated['reported_by'] = auth()->id();
        $validated['status']      = 'open';

        HostelComplaint::create($validated);

        return back()->with('success', 'Complaint registered successfully.');
    }

    public function update(Request $request, HostelComplaint $complaint)
    {
        abort_if($complaint->school_id !== app('current_school_id'), 403);

        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'status'           => 'required|in:open,in_progress,resolved,closed,rejected',
            'assigned_to'      => ['nullable', Rule::exists('users', 'id')->where('school_id', $schoolId)],
            'priority'         => 'sometimes|in:low,medium,high,urgent',
            'resolution_notes' => 'nullable|string',
        ]);

        if (in_array($validated['status'], ['resolved', 'closed']) && !$complaint->resolved_at) {
            $validated['resolved_at'] = now();
        }

        $complaint->update($validated);

        return back()->with('success', 'Complaint updated.');
    }

    public function destroy(HostelComplaint $complaint)
    {
        abort_if($complaint->school_id !== app('current_school_id'), 403);

        $complaint->delete();
        return back()->with('success', 'Complaint deleted.');
    }
}
