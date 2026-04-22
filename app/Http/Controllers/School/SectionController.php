<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\Section;
use App\Models\CourseClass;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SectionController extends Controller
{
    public function index()
    {
        $school   = app('current_school');
        $sections = Section::with('courseClass.department')
            ->where('school_id', $school->id)
            ->forCurrentYear()
            ->orderBy('course_class_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $classes  = CourseClass::with('department')
            ->where('school_id', $school->id)
            ->orderBy('numeric_value')
            ->orderBy('name')
            ->get();

        return Inertia::render('School/Academics/Sections', [
            'sections' => $sections,
            'classes'  => $classes,
        ]);
    }

    public function store(Request $request, ChatService $chatService)
    {
        $school    = app('current_school');
        $validated = $request->validate([
            'course_class_id' => ['required', Rule::exists('course_classes', 'id')->where('school_id', $school->id)],
            'name'            => [
                'required', 'string', 'max:255',
                Rule::unique('sections')->where(fn($q) => $q->where('school_id', $school->id)->where('course_class_id', $request->course_class_id)->whereNull('deleted_at'))
            ],
            'capacity'        => 'nullable|integer|min:1',
            'sort_order'      => 'nullable|integer|min:0',
        ]);
        $validated['school_id']   = $school->id;
        $validated['sort_order']  = $validated['sort_order'] ?? 0;
        $section = Section::create($validated);
        $section->load('courseClass');

        // Attach to the current academic year so it appears in this year's
        // dropdowns. Past years are untouched.
        if (app()->bound('current_academic_year_id')) {
            $section->academicYears()->syncWithoutDetaching([app('current_academic_year_id')]);
        }

        // Auto-create section chat group
        $chatService->ensureSectionGroup($section, $school->id);

        return redirect()->back()->with('status', 'Section created successfully.');
    }

    public function update(Request $request, Section $section)
    {
        if ($section->school_id !== app('current_school')->id) abort(403);

        $validated = $request->validate([
            'course_class_id' => ['required', Rule::exists('course_classes', 'id')->where('school_id', $section->school_id)],
            'name'            => [
                'required', 'string', 'max:255',
                Rule::unique('sections')->where(fn($q) => $q->where('school_id', $section->school_id)->where('course_class_id', $request->course_class_id)->whereNull('deleted_at'))->ignore($section->id)
            ],
            'capacity'        => 'nullable|integer|min:1',
            'sort_order'      => 'nullable|integer|min:0',
        ]);
        $section->update($validated);
        $section->load('courseClass');

        // Sync group name if it exists
        ChatConversation::where('section_id', $section->id)
            ->where('group_type', 'section_group')
            ->update(['name' => ($section->courseClass->name ?? '') . ' - ' . $section->name]);

        return redirect()->back()->with('status', 'Section updated successfully.');
    }

    public function destroy(Section $section)
    {
        if ($section->school_id !== app('current_school')->id) abort(403);

        // Detach from the current academic year only — past-year histories,
        // attendance, exam records keep their section_id reference intact.
        // If the section has no remaining year attachments, soft-delete it.
        $currentYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        if ($currentYearId) {
            $section->academicYears()->detach($currentYearId);
        }

        if ($section->academicYears()->count() === 0) {
            $section->delete();
            return redirect()->back()->with('status', 'Section removed and archived.');
        }

        return redirect()->back()->with('status', 'Section removed from this academic year.');
    }

    public function reorder(Request $request)
    {
        $school = app('current_school');
        foreach ($request->input('order', []) as $item) {
            Section::where('id', $item['id'])->where('school_id', $school->id)
                ->update(['sort_order' => $item['order']]);
        }
        return response()->json(['ok' => true]);
    }
}
