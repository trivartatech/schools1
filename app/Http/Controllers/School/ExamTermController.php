<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ExamTerm;
use Illuminate\Http\Request;
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
        ]);

        ExamTerm::create([
            'school_id'        => app('current_school_id'),
            'academic_year_id' => app('current_academic_year_id'),
            'name'             => $validated['name'],
            'display_name'     => $validated['display_name'],
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
}
