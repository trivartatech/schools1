<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Services\TeacherScopeService;
use App\Traits\Exportable;
use Illuminate\Http\Request;

class AssignmentExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $scope          = app(TeacherScopeService::class)->for(auth()->user());

        $query = Assignment::with(['courseClass', 'section', 'subject', 'teacher.user'])
            ->withCount('submissions')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId);

        if ($scope->restricted) {
            $query->whereIn('section_id', $scope->sectionIds->isEmpty() ? [-1] : $scope->sectionIds);
            if ($scope->subjectRestricted) {
                $query->whereIn('subject_id', $scope->subjectIds->isEmpty() ? [-1] : $scope->subjectIds);
            }
        }

        if ($request->filled('class_id'))   $query->where('class_id', $request->class_id);
        if ($request->filled('subject_id')) $query->where('subject_id', $request->subject_id);
        if ($request->filled('status'))     $query->where('status', $request->status);

        $assignments = $query->latest()->get();

        $headers = ['S.No', 'Title', 'Class', 'Section', 'Subject', 'Teacher', 'Due Date', 'Status', 'Submissions'];

        $rows = [];
        foreach ($assignments as $i => $a) {
            $rows[] = [
                $i + 1,
                $a->title,
                $a->courseClass?->name ?? '',
                $a->section?->name ?? '',
                $a->subject?->name ?? '',
                $a->teacher?->user?->name ?? '',
                $a->due_date,
                ucfirst($a->status),
                $a->submissions_count,
            ];
        }

        return $this->exportResponse($request, $headers, $rows, 'assignments-export-' . now()->format('Y-m-d'));
    }
}
