<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Subject;
use App\Models\SubjectType;
use Inertia\Inertia;

class SubjectController extends Controller
{
    public function index()
    {
        $school       = app('current_school');
        $subjects     = Subject::with('subjectType')
            ->where('school_id', $school->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'id'               => $s->id,
                'name'             => $s->name,
                'code'             => $s->code,
                'part'             => $s->part,
                'subject_type_id'  => $s->subject_type_id,
                'type'             => $s->type,
                'is_elective'      => $s->is_elective,
                'is_co_scholastic' => $s->is_co_scholastic,
                'sort_order'       => $s->sort_order,
                'subject_type'     => $s->subjectType ? ['id' => $s->subjectType->id, 'label' => $s->subjectType->label] : null,
            ]);

        $subjectTypes = SubjectType::where('school_id', $school->id)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'label', 'sort_order']);

        return Inertia::render('School/Academics/Subjects', [
            'subjects'     => $subjects,
            'subjectTypes' => $subjectTypes,
        ]);
    }

    public function store(Request $request)
    {
        $school    = app('current_school');
        $validated = $request->validate([
            'name'             => [
                'required', 'string', 'max:255',
                Rule::unique('subjects')->where(fn ($q) => $q->where('school_id', $school->id)->whereNull('deleted_at')),
            ],
            'code'             => [
                'nullable', 'string', 'max:50',
                Rule::unique('subjects')->where(fn ($q) => $q->where('school_id', $school->id)->whereNull('deleted_at'))->whereNotNull('code'),
            ],
            'subject_type_id'  => ['nullable', Rule::exists('subject_types', 'id')->where('school_id', $school->id)],
            'type'             => 'required|in:theory,practical',
            'is_elective'      => 'boolean',
            'is_co_scholastic' => 'boolean',
            'sort_order'       => 'nullable|integer|min:0',
        ]);

        $validated['school_id']  = $school->id;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        // Sync `part` label from subject type for backward-compat
        if (!empty($validated['subject_type_id'])) {
            $type = SubjectType::find($validated['subject_type_id']);
            $validated['part'] = $type?->label;
        } else {
            $validated['part'] = null;
        }

        Subject::create($validated);

        return redirect()->back()->with('status', 'Subject created successfully.');
    }

    public function update(Request $request, Subject $subject)
    {
        if ($subject->school_id !== app('current_school')->id) abort(403);
        $school = app('current_school');

        $validated = $request->validate([
            'name'             => [
                'required', 'string', 'max:255',
                Rule::unique('subjects')
                    ->where(fn ($q) => $q->where('school_id', $school->id)->whereNull('deleted_at'))
                    ->ignore($subject->id),
            ],
            'code'             => [
                'nullable', 'string', 'max:50',
                Rule::unique('subjects')
                    ->where(fn ($q) => $q->where('school_id', $school->id)->whereNull('deleted_at'))
                    ->whereNotNull('code')
                    ->ignore($subject->id),
            ],
            'subject_type_id'  => ['nullable', Rule::exists('subject_types', 'id')->where('school_id', $school->id)],
            'type'             => 'required|in:theory,practical',
            'is_elective'      => 'boolean',
            'is_co_scholastic' => 'boolean',
            'sort_order'       => 'nullable|integer|min:0',
        ]);

        // Sync `part` label from subject type for backward-compat
        if (!empty($validated['subject_type_id'])) {
            $type = SubjectType::find($validated['subject_type_id']);
            $validated['part'] = $type?->label;
        } else {
            $validated['part'] = null;
        }

        $subject->update($validated);

        return redirect()->back()->with('status', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject)
    {
        if ($subject->school_id !== app('current_school')->id) abort(403);
        $subject->delete();
        return redirect()->back()->with('status', 'Subject deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $school = app('current_school');
        foreach ($request->input('order', []) as $item) {
            Subject::where('id', $item['id'])->where('school_id', $school->id)
                ->update(['sort_order' => $item['order']]);
        }
        return response()->json(['ok' => true]);
    }
}
