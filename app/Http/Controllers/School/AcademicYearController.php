<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\AcademicYear;

class AcademicYearController extends Controller
{
    /**
     * Display a listing of the academic years.
     */
    public function index()
    {
        $schoolId = app('current_school')->id;
        $academicYears = AcademicYear::where('school_id', $schoolId)->orderBy('start_date', 'desc')->get();

        return Inertia::render('School/Academics/AcademicYears', [
            'academicYears' => $academicYears
        ]);
    }

    /**
     * Store a newly created academic year.
     */
    public function store(Request $request)
    {
        $schoolId = app('current_school')->id;

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'boolean',
        ]);

        if (!empty($validated['is_current'])) {
            AcademicYear::where('school_id', $schoolId)->update(['is_current' => false]);
        } else {
            $validated['is_current'] = false;
        }

        $validated['school_id'] = $schoolId;
        $validated['status'] = 'active';

        AcademicYear::create($validated);

        return redirect()->back()->with('success', 'Academic Year created successfully.');
    }

    /**
     * Update the specified academic year.
     */
    public function update(Request $request, AcademicYear $academicYear)
    {
        if ($academicYear->school_id !== app('current_school')->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'boolean',
            'status' => 'required|in:active,frozen',
        ]);

        if (!empty($validated['is_current'])) {
            AcademicYear::where('school_id', $academicYear->school_id)
                ->where('id', '!=', $academicYear->id)
                ->update(['is_current' => false]);
        }

        $academicYear->update($validated);

        return redirect()->back()->with('success', 'Academic Year updated successfully.');
    }

    /**
     * Remove the specified academic year.
     */
    public function destroy(AcademicYear $academicYear)
    {
        if ($academicYear->school_id !== app('current_school')->id) {
            abort(403);
        }

        $academicYear->delete();

        return redirect()->back()->with('success', 'Academic Year deleted successfully.');
    }
}
