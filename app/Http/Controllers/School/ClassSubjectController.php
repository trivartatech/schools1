<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\ClassSubject;
use App\Models\CourseClass;
use App\Models\Section;
use App\Models\Subject;

class ClassSubjectController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school')->id;

        $assignments = ClassSubject::where('class_subjects.school_id', $schoolId)
            ->with([
                'courseClass',
                'courseClass.department',
                'section',
                'subject',
                'subject.subjectType',
            ])
            ->join('course_classes', 'class_subjects.course_class_id', '=', 'course_classes.id')
            ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
            ->orderBy('course_classes.numeric_value')
            ->orderBy('course_classes.name')
            ->orderBy('subjects.sort_order')
            ->orderBy('subjects.name')
            ->select('class_subjects.*')
            ->get();

        $classes  = CourseClass::where('school_id', $schoolId)->with('department')->orderBy('numeric_value')->get();
        $sections = Section::where('school_id', $schoolId)->with('courseClass')->get();
        $subjects = Subject::where('school_id', $schoolId)->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('School/Academics/ClassSubjects', [
            'assignments' => $assignments,
            'classes'     => $classes,
            'sections'    => $sections,
            'subjects'    => $subjects,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school')->id;

        $validated = $request->validate([
            'course_class_id'  => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_id'       => ['nullable', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'subject_ids'      => 'required|array|min:1',
            'subject_ids.*'    => [Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'is_co_scholastic' => 'boolean',
        ]);

        // If a section is provided, validate it belongs to the selected class
        if (!empty($validated['section_id'])) {
            $sectionBelongs = Section::where('id', $validated['section_id'])
                ->where('course_class_id', $validated['course_class_id'])
                ->exists();
            if (!$sectionBelongs) {
                return back()->withErrors(['section_id' => 'Selected section does not belong to the chosen class.']);
            }
        }

        // Fetch already-assigned subject IDs in one query (N+1 fix)
        $existingSubjectIds = ClassSubject::where('school_id', $schoolId)
            ->where('course_class_id', $validated['course_class_id'])
            ->where('section_id', $validated['section_id'] ?? null)
            ->whereIn('subject_id', $validated['subject_ids'])
            ->pluck('subject_id')
            ->flip();   // O(1) lookup

        $now      = now();
        $toInsert = [];
        foreach ($validated['subject_ids'] as $subjectId) {
            if ($existingSubjectIds->has($subjectId)) continue;
            $toInsert[] = [
                'school_id'        => $schoolId,
                'course_class_id'  => $validated['course_class_id'],
                'section_id'       => $validated['section_id'] ?? null,
                'subject_id'       => $subjectId,
                'is_co_scholastic' => $validated['is_co_scholastic'] ?? false,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        if (!empty($toInsert)) {
            ClassSubject::insert($toInsert);
        }

        $added   = count($toInsert);
        $skipped = count($validated['subject_ids']) - $added;
        $msg     = "{$added} subject(s) assigned successfully.";
        if ($skipped > 0) $msg .= " {$skipped} already assigned (skipped).";

        return redirect()->back()->with('status', $msg);
    }

    public function update(Request $request, ClassSubject $classSubject)
    {
        if ($classSubject->school_id !== app('current_school')->id) abort(403);

        $validated = $request->validate([
            'is_co_scholastic' => 'boolean',
        ]);

        $classSubject->update($validated);

        return redirect()->back()->with('status', 'Assignment updated successfully.');
    }

    public function destroy(ClassSubject $classSubject)
    {
        if ($classSubject->school_id !== app('current_school')->id) abort(403);
        $classSubject->delete();

        return redirect()->back()->with('status', 'Assignment removed successfully.');
    }

    /**
     * Return sections that belong to a given class (for dynamic dropdowns).
     */
    public function sectionsForClass(Request $request, $classId)
    {
        $schoolId = app('current_school')->id;
        $sections = Section::where('school_id', $schoolId)
            ->where('course_class_id', $classId)
            ->get(['id', 'name']);

        return response()->json($sections);
    }
}
