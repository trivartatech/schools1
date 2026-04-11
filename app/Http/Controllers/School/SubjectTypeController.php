<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Subject;
use App\Models\SubjectType;
use Inertia\Inertia;

class SubjectTypeController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school')->id;
        $types = SubjectType::where('school_id', $schoolId)
            ->withCount(['subjects' => fn ($q) => $q->where('school_id', $schoolId)])
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return Inertia::render('School/Academics/SubjectTypes', ['types' => $types]);
    }

    public function store(Request $request)
    {
        $schoolId  = app('current_school')->id;
        $validated = $request->validate([
            'label'       => [
                'required', 'string', 'max:100',
                Rule::unique('subject_types')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'description' => 'nullable|string|max:255',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $validated['school_id'] = $schoolId;
        SubjectType::create($validated);

        return redirect()->back()->with('status', 'Subject type added.');
    }

    public function update(Request $request, SubjectType $subjectType)
    {
        if ($subjectType->school_id !== app('current_school')->id) abort(403);

        $validated = $request->validate([
            'label'       => [
                'required', 'string', 'max:100',
                Rule::unique('subject_types')
                    ->where(fn ($q) => $q->where('school_id', $subjectType->school_id))
                    ->ignore($subjectType->id),
            ],
            'description' => 'nullable|string|max:255',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $subjectType->update($validated);

        // Sync the `part` label on all subjects that use this type
        Subject::where('subject_type_id', $subjectType->id)
            ->update(['part' => $validated['label']]);

        return redirect()->back()->with('status', 'Subject type updated.');
    }

    public function destroy(SubjectType $subjectType)
    {
        if ($subjectType->school_id !== app('current_school')->id) abort(403);

        $inUse = Subject::where('subject_type_id', $subjectType->id)->count();
        if ($inUse > 0) {
            return redirect()->back()->withErrors([
                'delete' => "Cannot delete — {$inUse} subject(s) are using this type. Re-assign or delete those subjects first.",
            ]);
        }

        $subjectType->delete();
        return redirect()->back()->with('status', 'Subject type deleted.');
    }
}
