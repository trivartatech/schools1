<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ExamTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ExamTermController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->can('manage_exam_terms')) {
            abort(403, 'You do not have permission to manage exam terms.');
        }

        $terms = ExamTerm::where('school_id', app('current_school_id'))
            ->where('academic_year_id', app('current_academic_year_id'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('School/Examinations/Terms/Index', [
            'terms' => $terms
        ]);
    }

    public function store(Request $request)
    {
        if (! $request->user()->can('manage_exam_terms')) {
            abort(403, 'You do not have permission to manage exam terms.');
        }

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'sort_order'   => 'nullable|integer|min:0|max:65535',
        ]);

        // Auto-assign next available rank when the admin leaves it blank.
        if (! isset($validated['sort_order'])) {
            $validated['sort_order'] = (int) ExamTerm::where('school_id', app('current_school_id'))
                ->where('academic_year_id', app('current_academic_year_id'))
                ->max('sort_order') + 1;
        }

        ExamTerm::create([
            'school_id'        => app('current_school_id'),
            'academic_year_id' => app('current_academic_year_id'),
            'name'             => $validated['name'],
            'display_name'     => $validated['display_name'] ?? null,
            'sort_order'       => $validated['sort_order'],
        ]);

        return back()->with('success', 'Exam Term created successfully.');
    }

    public function update(Request $request, ExamTerm $examTerm)
    {
        if (! $request->user()->can('manage_exam_terms')) {
            abort(403, 'You do not have permission to manage exam terms.');
        }
        abort_if($examTerm->school_id !== app('current_school_id'), 403, 'Unauthorized.');

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'sort_order'   => 'nullable|integer|min:0|max:65535',
        ]);

        $examTerm->update($validated);

        return back()->with('success', 'Exam Term updated successfully.');
    }

    public function destroy(Request $request, ExamTerm $examTerm)
    {
        if (! $request->user()->can('manage_exam_terms')) {
            abort(403, 'You do not have permission to manage exam terms.');
        }
        abort_if($examTerm->school_id !== app('current_school_id'), 403, 'Unauthorized.');

        if ($examTerm->examTypes()->exists()) {
            return back()->with('error', 'Cannot delete term that has associated exam types.');
        }

        $examTerm->delete();

        return back()->with('success', 'Exam Term deleted successfully.');
    }

    /**
     * Bulk re-rank exam terms based on the order in which their ids are
     * supplied. Position in the array becomes the new sort_order (1-based).
     * Used by the drag-and-drop reordering on the Terms list page.
     */
    public function reorder(Request $request)
    {
        if (! $request->user()->can('manage_exam_terms')) {
            abort(403, 'You do not have permission to manage exam terms.');
        }

        $validated = $request->validate([
            'order'   => 'required|array|min:1',
            'order.*' => ['integer', \Illuminate\Validation\Rule::exists('exam_terms', 'id')
                ->where('school_id', app('current_school_id'))
                ->where('academic_year_id', app('current_academic_year_id'))],
        ]);

        $schoolId = app('current_school_id');
        $yearId   = app('current_academic_year_id');

        DB::transaction(function () use ($validated, $schoolId, $yearId) {
            foreach ($validated['order'] as $position => $id) {
                ExamTerm::where('id', $id)
                    ->where('school_id', $schoolId)
                    ->where('academic_year_id', $yearId)
                    ->update(['sort_order' => $position + 1]);
            }
        });

        return back()->with('success', 'Term order updated.');
    }
}
