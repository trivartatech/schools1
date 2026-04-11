<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\GradingSystem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GradingSystemController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->can('manage_exam_grades')) {
            abort(403, 'You do not have permission to manage grading systems.');
        }

        $systems = GradingSystem::with('grades')
            ->where('school_id', app('current_school_id'))
            ->where('academic_year_id', app('current_academic_year_id'))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return Inertia::render('School/Examinations/Grades/Index', [
            'gradingSystems' => $systems
        ]);
    }

    public function store(Request $request)
    {
        if (! $request->user()->can('manage_exam_grades')) {
            abort(403);
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:scholastic,co_scholastic',
            'description' => 'nullable|string',
            'grades'      => 'nullable|array',
            'grades.*.name'           => 'required|string|max:50',
            'grades.*.min_percentage' => 'required|numeric|min:0|max:100',
            'grades.*.max_percentage' => 'required|numeric|min:0|max:100',
            'grades.*.grade_point'    => 'nullable|numeric|min:0',
            'grades.*.description'    => 'nullable|string|max:255',
            'grades.*.color_code'     => 'nullable|string|max:7',
            'grades.*.is_fail'        => 'nullable|boolean',
        ]);

        $system = GradingSystem::create([
            'school_id'        => app('current_school_id'),
            'academic_year_id' => app('current_academic_year_id'),
            'name'             => $validated['name'],
            'type'             => $validated['type'],
            'description'      => $validated['description'] ?? null,
        ]);

        if (!empty($validated['grades'])) {
            foreach ($validated['grades'] as $g) {
                $system->grades()->create(array_merge($g, [
                    'school_id' => app('current_school_id')
                ]));
            }
        }

        return redirect()->route('school.grading-systems.index')->with('success', 'Grading System created successfully.');
    }

    public function update(Request $request, GradingSystem $gradingSystem)
    {
        if (! $request->user()->can('manage_exam_grades')) {
            abort(403);
        }
        abort_if($gradingSystem->school_id !== app('current_school_id'), 403, 'Unauthorized.');

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:scholastic,co_scholastic',
            'description' => 'nullable|string',
            'grades'      => 'nullable|array',
            'grades.*.id'             => 'nullable|exists:grades,id',
            'grades.*.name'           => 'required|string|max:50',
            'grades.*.min_percentage' => 'required|numeric|min:0|max:100',
            'grades.*.max_percentage' => 'required|numeric|min:0|max:100',
            'grades.*.grade_point'    => 'nullable|numeric|min:0',
            'grades.*.description'    => 'nullable|string|max:255',
            'grades.*.color_code'     => 'nullable|string|max:7',
            'grades.*.is_fail'        => 'nullable|boolean',
        ]);

        $gradingSystem->update([
            'name'        => $validated['name'],
            'type'        => $validated['type'],
            'description' => $validated['description'] ?? null,
        ]);

        if (isset($validated['grades'])) {
            // Delete old grades that are not in the new payload
            $keptIds = collect($validated['grades'])->pluck('id')->filter()->toArray();
            $gradingSystem->grades()->whereNotIn('id', $keptIds)->delete();

            // Create or update grades
            foreach ($validated['grades'] as $g) {
                if (isset($g['id'])) {
                    $gradingSystem->grades()->where('id', $g['id'])->update([
                        'name'           => $g['name'],
                        'min_percentage' => $g['min_percentage'],
                        'max_percentage' => $g['max_percentage'],
                        'grade_point'    => $g['grade_point'] ?? null,
                        'description'    => $g['description'] ?? null,
                        'color_code'     => $g['color_code'] ?? null,
                        'is_fail'        => $g['is_fail'] ?? false,
                    ]);
                } else {
                    $gradingSystem->grades()->create(array_merge($g, [
                        'school_id' => app('current_school_id')
                    ]));
                }
            }
        } else {
            // If empty array passed, wipe all grades
            $gradingSystem->grades()->delete();
        }

        return redirect()->route('school.grading-systems.index')->with('success', 'Grading System updated successfully.');
    }

    public function destroy(Request $request, GradingSystem $gradingSystem)
    {
        if (! $request->user()->can('manage_exam_grades')) {
            abort(403);
        }
        abort_if($gradingSystem->school_id !== app('current_school_id'), 403, 'Unauthorized.');

        $gradingSystem->delete();
        return redirect()->route('school.grading-systems.index')->with('success', 'Grading System deleted successfully.');
    }
}
