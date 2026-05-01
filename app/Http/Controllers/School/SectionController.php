<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\Section;
use App\Models\CourseClass;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $school        = app('current_school');
        $currentYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $validated = $request->validate([
            'course_class_id' => ['required', Rule::exists('course_classes', 'id')->where('school_id', $school->id)],
            'name'            => [
                'required', 'string', 'max:255',
                // Block only when an active section with this name is already
                // attached to the CURRENT academic year — that's a real
                // duplicate. A row with the same name from a past year (no
                // longer attached to this year) is reused below instead of
                // creating a duplicate, since sections are designed to live
                // across multiple years via the section_academic_year pivot.
                function ($attribute, $value, $fail) use ($school, $request, $currentYearId) {
                    if (!$currentYearId) return;
                    $exists = Section::where('school_id', $school->id)
                        ->where('course_class_id', $request->course_class_id)
                        ->where('name', $value)
                        ->forYear($currentYearId)
                        ->exists();
                    if ($exists) {
                        $fail('A section with this name already exists in the current academic year.');
                    }
                },
            ],
            'capacity'        => 'nullable|integer|min:1',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        // Reuse path: an existing (school, class, name) row — possibly from a
        // previous academic year, possibly soft-deleted — already exists.
        // Restore + reattach instead of inserting a duplicate, which the
        // unique index (school_id, course_class_id, name) would reject anyway.
        // The transaction returns the section AND which path it took, so the
        // caller can show an outcome-specific toast.
        $result = DB::transaction(function () use ($validated, $school) {
            $existing = Section::withTrashed()
                ->where('school_id', $school->id)
                ->where('course_class_id', $validated['course_class_id'])
                ->where('name', $validated['name'])
                ->first();

            if ($existing) {
                $wasTrashed = $existing->trashed();
                if ($wasTrashed) {
                    $existing->restore();
                }
                $updates = [];
                if (array_key_exists('capacity', $validated) && $validated['capacity'] !== null) {
                    $updates['capacity'] = $validated['capacity'];
                }
                if (array_key_exists('sort_order', $validated) && $validated['sort_order'] !== null) {
                    $updates['sort_order'] = $validated['sort_order'];
                }
                if (!empty($updates)) {
                    $existing->update($updates);
                }
                return ['section' => $existing, 'outcome' => $wasTrashed ? 'restored' : 'reactivated'];
            }

            $new = Section::create([
                'school_id'       => $school->id,
                'course_class_id' => $validated['course_class_id'],
                'name'            => $validated['name'],
                'capacity'        => $validated['capacity'] ?? null,
                'sort_order'      => $validated['sort_order'] ?? 0,
            ]);

            return ['section' => $new, 'outcome' => 'created'];
        });

        $section = $result['section'];
        $section->load('courseClass');

        // Attach to the current academic year so it appears in this year's
        // dropdowns. Past years are untouched. syncWithoutDetaching is
        // idempotent — re-attaching an already-attached year is a no-op.
        if ($currentYearId) {
            $section->academicYears()->syncWithoutDetaching([$currentYearId]);
        }

        // Auto-create section chat group
        $chatService->ensureSectionGroup($section, $school->id);

        // Outcome-specific toast: the user gets context for what actually
        // happened — useful when reusing a row from a past year so they
        // don't think "did it just silently keep the old capacity?"
        $label = ($section->courseClass?->name ?? '') . ($section->courseClass?->name ? ' — ' : '') . $section->name;
        $message = match ($result['outcome']) {
            'created'     => "Section '{$label}' created.",
            'reactivated' => "Section '{$label}' already existed from a previous year — reattached to this academic year.",
            'restored'    => "Section '{$label}' was archived earlier — restored and attached to this academic year.",
        };

        return redirect()->back()->with('success', $message);
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

        $label = ($section->courseClass?->name ?? '') . ($section->courseClass?->name ? ' — ' : '') . $section->name;
        return redirect()->back()->with('success', "Section '{$label}' updated.");
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
            return redirect()->back()->with('success', "Section '{$section->name}' removed and archived.");
        }

        return redirect()->back()->with('success', "Section '{$section->name}' removed from this academic year.");
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
