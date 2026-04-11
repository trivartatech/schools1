<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\AcademicYear;
use App\Services\AcademicRolloverService;

class RolloverController extends Controller
{
    /**
     * Display the Rollover Wizard UI
     */
    public function index()
    {
        $schoolId = app('current_school')->id;

        // Get all years for this school, ordered descending
        $years = AcademicYear::where('school_id', $schoolId)
                    ->orderBy('start_date', 'desc')
                    ->get();

        return Inertia::render('School/Setup/RolloverWizard', [
            'years' => $years
        ]);
    }

    /**
     * Execute the rollover process
     */
    public function execute(Request $request, AcademicRolloverService $rolloverService)
    {
        $school = app('current_school');

        $validated = $request->validate([
            'source_year_id' => 'required|exists:academic_years,id',
            'target_year_id' => 'required|exists:academic_years,id|different:source_year_id',
            'modules'        => 'required|array|min:1',
            'modules.*'      => 'string|in:departments,classes,subjects'
        ]);

        $sourceYear = AcademicYear::where('school_id', $school->id)->findOrFail($validated['source_year_id']);
        $targetYear = AcademicYear::where('school_id', $school->id)->findOrFail($validated['target_year_id']);

        // Quick Conflict Check: Prevent cloning classes if the target year already has classes
        if (in_array('classes', $validated['modules'])) {
            $existingClasses = \App\Models\CourseClass::where('school_id', $school->id)
                                ->where('academic_year_id', $targetYear->id)
                                ->exists();
            if ($existingClasses) {
                return back()->with('error', 'Conflict: Target year already has Classes defined. Cannot clone over existing data.');
            }
        }

        if (in_array('subjects', $validated['modules'])) {
            $existingSubjects = \App\Models\Subject::where('school_id', $school->id)
                                ->where('academic_year_id', $targetYear->id)
                                ->exists();
            if ($existingSubjects) {
                return back()->with('error', 'Conflict: Target year already has Subjects defined.');
            }
        }

        try {
            $rolloverService->execute($school, $sourceYear, $targetYear, $validated['modules']);
            return back()->with('success', 'Academic Year Rollover completed successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Rollover failed: ' . $e->getMessage());
        }
    }
}
