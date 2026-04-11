<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Models\StudentDiary;
use App\Services\TeacherScopeService;
use App\Traits\Exportable;
use Illuminate\Http\Request;

class DiaryExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $scope          = app(TeacherScopeService::class)->for(auth()->user());

        $query = StudentDiary::with(['courseClass', 'section', 'subject', 'teacher.user'])
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId);

        if ($scope->restricted) {
            $query->whereIn('section_id', $scope->sectionIds->isEmpty() ? [-1] : $scope->sectionIds);
            if ($scope->subjectRestricted) {
                $query->whereIn('subject_id', $scope->subjectIds->isEmpty() ? [-1] : $scope->subjectIds);
            }
        }

        if ($request->filled('class_id'))   $query->where('class_id', $request->class_id);
        if ($request->filled('section_id')) $query->where('section_id', $request->section_id);
        if ($request->filled('date'))       $query->where('date', $request->date);
        if ($request->filled('from'))       $query->where('date', '>=', $request->from);
        if ($request->filled('to'))         $query->where('date', '<=', $request->to);

        $entries = $query->latest('date')->get();

        $headers = ['S.No', 'Date', 'Class', 'Section', 'Subject', 'Teacher', 'Content'];

        $rows = [];
        foreach ($entries as $i => $e) {
            $rows[] = [
                $i + 1,
                $e->date,
                $e->courseClass?->name ?? '',
                $e->section?->name ?? '',
                $e->subject?->name ?? '',
                $e->teacher?->user?->name ?? '',
                strip_tags($e->content ?? ''),
            ];
        }

        return $this->exportResponse($request, $headers, $rows, 'diary-export-' . now()->format('Y-m-d'), [
            'orientation' => 'landscape',
        ]);
    }
}
