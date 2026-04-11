<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ExamAssessment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExamAssessmentController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->can('manage_exam_terms')) {
            abort(403);
        }

        $assessments = ExamAssessment::with('items')
            ->where('school_id', app('current_school_id'))
            ->where('academic_year_id', app('current_academic_year_id'))
            ->orderBy('name')
            ->get();

        return Inertia::render('School/Examinations/Assessments/Index', [
            'assessments' => $assessments,
        ]);
    }

    public function store(Request $request)
    {
        if (! $request->user()->can('manage_exam_terms')) {
            abort(403);
        }

        $validated = $request->validate([
            'name'            => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('exam_assessments')->where(function ($query) {
                    return $query->where('school_id', app('current_school_id'))
                                 ->where('academic_year_id', app('current_academic_year_id'));
                }),
            ],
            'description'     => 'nullable|string',
            'items'           => 'nullable|array',
            'items.*.name'    => 'required|string|max:255',
            'items.*.code'    => 'nullable|string|max:50',
        ]);

        $assessment = ExamAssessment::create([
            'school_id'        => app('current_school_id'),
            'academic_year_id' => app('current_academic_year_id'),
            'name'             => $validated['name'],
            'description'      => $validated['description'] ?? null,
        ]);

        if (!empty($validated['items'])) {
            foreach ($validated['items'] as $index => $item) {
                $assessment->items()->create([
                    'school_id'  => app('current_school_id'),
                    'name'       => $item['name'],
                    'code'       => $item['code'] ?? null,
                    'sort_order' => $index,
                ]);
            }
        }

        return redirect()->route('school.exam-assessments.index')->with('success', 'Exam Assessment created successfully.');
    }

    public function update(Request $request, ExamAssessment $examAssessment)
    {
        if (! $request->user()->can('manage_exam_terms')) {
            abort(403);
        }
        abort_if($examAssessment->school_id !== app('current_school_id'), 403, 'Unauthorized.');

        $validated = $request->validate([
            'name'            => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('exam_assessments')->ignore($examAssessment->id)->where(function ($query) {
                    return $query->where('school_id', app('current_school_id'))
                                 ->where('academic_year_id', app('current_academic_year_id'));
                }),
            ],
            'description'     => 'nullable|string',
            'items'           => 'nullable|array',
            'items.*.id'      => 'nullable|exists:exam_assessment_items,id',
            'items.*.name'    => 'required|string|max:255',
            'items.*.code'    => 'nullable|string|max:50',
        ]);

        $examAssessment->update([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (isset($validated['items'])) {
            $keptIds = collect($validated['items'])->pluck('id')->filter()->toArray();
            $examAssessment->items()->whereNotIn('id', $keptIds)->delete();

            foreach ($validated['items'] as $index => $item) {
                if (!empty($item['id'])) {
                    $examAssessment->items()->where('id', $item['id'])->update([
                        'name'       => $item['name'],
                        'code'       => $item['code'] ?? null,
                        'sort_order' => $index,
                    ]);
                } else {
                    $examAssessment->items()->create([
                        'school_id'  => app('current_school_id'),
                        'name'       => $item['name'],
                        'code'       => $item['code'] ?? null,
                        'sort_order' => $index,
                    ]);
                }
            }
        } else {
            $examAssessment->items()->delete();
        }

        return redirect()->route('school.exam-assessments.index')->with('success', 'Exam Assessment updated successfully.');
    }

    public function destroy(Request $request, ExamAssessment $examAssessment)
    {
        if (! $request->user()->can('manage_exam_terms')) {
            abort(403);
        }
        abort_if($examAssessment->school_id !== app('current_school_id'), 403, 'Unauthorized.');

        $examAssessment->items()->delete();
        $examAssessment->delete();

        return redirect()->route('school.exam-assessments.index')->with('success', 'Exam Assessment deleted successfully.');
    }
}
