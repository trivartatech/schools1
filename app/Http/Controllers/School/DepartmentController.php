<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index()
    {
        $school = app('current_school');
        $departments = Department::where('school_id', $school->id)->latest()->get();

        return Inertia::render('School/Academics/Departments', [
            'departments' => $departments,
        ]);
    }

    public function store(Request $request)
    {
        $school = app('current_school');
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
        ]);

        $validated['school_id'] = $school->id;

        Department::create($validated);

        return redirect()->back()->with('success', 'Department created successfully.');
    }

    public function update(Request $request, Department $department)
    {
        $school = app('current_school');
        
        if ($department->school_id !== $school->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
        ]);

        $department->update($validated);

        return redirect()->back()->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        $school = app('current_school');
        
        if ($department->school_id !== $school->id) {
            abort(403);
        }

        $department->delete();

        return redirect()->back()->with('success', 'Department deleted successfully.');
    }
}
