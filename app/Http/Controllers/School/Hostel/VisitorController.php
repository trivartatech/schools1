<?php

namespace App\Http\Controllers\School\Hostel;

use App\Http\Controllers\Controller;
use App\Models\HostelVisitor;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VisitorController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $query = HostelVisitor::where('school_id', $schoolId)
                    ->with(['student', 'staff.user']);

        if ($request->date) {
            $query->whereDate('date', $request->date);
        } else {
            $query->whereDate('date', date('Y-m-d'));
        }

        $visitors = $query->latest()->paginate(20);

        $activeHostelStudentIds = \App\Models\HostelStudent::where('school_id', $schoolId)
                    ->where('status', 'Active')
                    ->pluck('student_id');

        $students = Student::whereIn('id', $activeHostelStudentIds)
                           ->get(['id', 'first_name', 'last_name', 'admission_no']);
        $staff = \App\Models\Staff::where('school_id', $schoolId)->with('user:id,name')->get(['id', 'user_id', 'employee_id']);

        return Inertia::render('School/Hostel/Visitors/Index', [
            'visitors' => $visitors,
            'students' => $students,
            'staff' => $staff,
            'filters' => $request->only('date')
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'meet_user_type'  => 'required|in:Student,Staff',
            'student_id'      => ['required_if:meet_user_type,Student', 'nullable', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'staff_id'        => ['required_if:meet_user_type,Staff', 'nullable', Rule::exists('staff', 'id')->where('school_id', $schoolId)],
            'visitor_type'    => 'nullable|string|max:255',
            'visitor_count'   => 'required|integer|min:1|max:50',
            'visitor_name'    => 'required|string|max:255',
            'relation'        => 'nullable|string|max:255',
            'phone'           => 'nullable|string|max:20',
            'date'            => 'required|date',
            'in_time'         => 'required',
            'purpose'         => 'nullable|string|max:255',
            'id_proof'        => 'nullable|string|max:255',
            'id_proof_type'   => 'nullable|string|max:100',
        ]);

        $validated['school_id'] = $schoolId;
        $validated['is_approved'] = true;

        HostelVisitor::create($validated);

        return back()->with('success', 'Visitor logged successfully');
    }

    public function update(Request $request, HostelVisitor $visitor)
    {
        abort_if($visitor->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'out_time' => 'required',
            'remarks'  => 'nullable|string'
        ]);

        $visitor->update($validated);
        return back()->with('success', 'Visitor out-time updated');
    }

    public function uploadPhoto(Request $request, HostelVisitor $visitor)
    {
        abort_if($visitor->school_id !== app('current_school_id'), 403);

        $request->validate([
            'photo_data' => 'required|string',
        ]);

        $base64 = $request->photo_data;
        if (str_contains($base64, ',')) {
            $base64 = explode(',', $base64)[1];
        }

        $decoded = base64_decode($base64);

        if (!$decoded || !getimagesizefromstring($decoded)) {
            return back()->with('error', 'Invalid image data provided.');
        }

        $filename = 'hostel/visitors/' . $visitor->id . '/photo_' . time() . '.jpg';
        Storage::disk('public')->put($filename, $decoded);

        $visitor->update(['visitor_photo' => $filename]);

        return back()->with('success', 'Visitor photo saved');
    }
}
