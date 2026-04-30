<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ExamType;
use App\Models\ExamTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ExamTypeController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->can('manage_exam_types')) {
            abort(403, 'You do not have permission to manage exam types.');
        }

        $types = ExamType::with('examTerm')
            ->where('school_id', app('current_school_id'))
            ->where('academic_year_id', app('current_academic_year_id'))
            ->orderBy('exam_term_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $terms = ExamTerm::where('school_id', app('current_school_id'))
            ->where('academic_year_id', app('current_academic_year_id'))
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name', 'display_name']);

        return Inertia::render('School/Examinations/Types/Index', [
            'types' => $types,
            'terms' => $terms,
        ]);
    }

    public function store(Request $request)
    {
        if (! $request->user()->can('manage_exam_types')) {
            abort(403, 'You do not have permission to manage exam types.');
        }

        $validated = $request->validate([
            'exam_term_id'   => ['required', \Illuminate\Validation\Rule::exists('exam_terms', 'id')->where('school_id', app('current_school_id'))->where('academic_year_id', app('current_academic_year_id'))],
            'name'           => 'required|string|max:255',
            'code'           => 'nullable|string|max:50',
            'display_name'   => 'nullable|string|max:255',
            'classification' => 'required|in:main,periodic,unit_test',
            'sort_order'     => 'nullable|integer|min:0|max:65535',
        ]);

        // Auto-assign next available rank within the chosen term.
        if (! isset($validated['sort_order'])) {
            $validated['sort_order'] = (int) ExamType::where('school_id', app('current_school_id'))
                ->where('academic_year_id', app('current_academic_year_id'))
                ->where('exam_term_id', $validated['exam_term_id'])
                ->max('sort_order') + 1;
        }

        ExamType::create(array_merge($validated, [
            'school_id'        => app('current_school_id'),
            'academic_year_id' => app('current_academic_year_id'),
        ]));

        return back()->with('success', 'Exam Type created successfully.');
    }

    public function update(Request $request, ExamType $examType)
    {
        if (! $request->user()->can('manage_exam_types')) {
            abort(403, 'You do not have permission to manage exam types.');
        }
        abort_if($examType->school_id !== app('current_school_id'), 403, 'Unauthorized.');

        $validated = $request->validate([
            'exam_term_id'   => ['required', \Illuminate\Validation\Rule::exists('exam_terms', 'id')->where('school_id', app('current_school_id'))->where('academic_year_id', app('current_academic_year_id'))],
            'name'           => 'required|string|max:255',
            'code'           => 'nullable|string|max:50',
            'display_name'   => 'nullable|string|max:255',
            'classification' => 'required|in:main,periodic,unit_test',
            'sort_order'     => 'nullable|integer|min:0|max:65535',
        ]);

        $examType->update($validated);

        return back()->with('success', 'Exam Type updated successfully.');
    }

    public function destroy(Request $request, ExamType $examType)
    {
        if (! $request->user()->can('manage_exam_types')) {
            abort(403, 'You do not have permission to manage exam types.');
        }
        abort_if($examType->school_id !== app('current_school_id'), 403, 'Unauthorized.');

        if (\App\Models\ExamSchedule::where('exam_type_id', $examType->id)->exists()) {
            return back()->with('error', 'Cannot delete this exam type because it has associated exam schedules. Delete those schedules first.');
        }

        $examType->delete();

        return back()->with('success', 'Exam Type deleted successfully.');
    }

    /**
     * Bulk re-rank exam types based on the order in which their ids are
     * supplied. Sort order is calculated *per term* — each term gets a
     * fresh 1-based sequence — so cross-term drags don't accidentally
     * leave gaps inside a single term's run.
     *
     * The endpoint expects the full ordered list of all visible types
     * (across all terms); the per-term counter handles the rest.
     */
    public function reorder(Request $request)
    {
        if (! $request->user()->can('manage_exam_types')) {
            abort(403, 'You do not have permission to manage exam types.');
        }

        $validated = $request->validate([
            'order'   => 'required|array|min:1',
            'order.*' => ['integer', \Illuminate\Validation\Rule::exists('exam_types', 'id')
                ->where('school_id', app('current_school_id'))
                ->where('academic_year_id', app('current_academic_year_id'))],
        ]);

        $schoolId = app('current_school_id');
        $yearId   = app('current_academic_year_id');

        $rows = ExamType::whereIn('id', $validated['order'])
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $yearId)
            ->get(['id', 'exam_term_id'])
            ->keyBy('id');

        DB::transaction(function () use ($validated, $rows) {
            $perTerm = []; // term_id => running counter
            foreach ($validated['order'] as $id) {
                $row = $rows->get($id);
                if (! $row) continue;
                $termId = $row->exam_term_id;
                $perTerm[$termId] = ($perTerm[$termId] ?? 0) + 1;
                ExamType::where('id', $id)->update(['sort_order' => $perTerm[$termId]]);
            }
        });

        return back()->with('success', 'Exam type order updated.');
    }
}
