<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\TeacherScopeService;
use App\Traits\Exportable;
use Illuminate\Http\Request;

class StudentExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $scope          = app(TeacherScopeService::class)->for(auth()->user());

        $query = Student::with(['studentParent', 'currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->where('school_id', $schoolId);

        if ($scope->restricted) {
            $query->whereHas('currentAcademicHistory', function ($q) use ($academicYearId, $scope) {
                $q->where('academic_year_id', $academicYearId)
                  ->where('status', 'current')
                  ->whereIn('section_id', $scope->sectionIds);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('admission_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('class_id')) {
            $query->whereHas('currentAcademicHistory', fn($q) => $q->where('class_id', $request->class_id));
        }
        if ($request->filled('section_id')) {
            $query->whereHas('currentAcademicHistory', fn($q) => $q->where('section_id', $request->section_id));
        }
        if ($request->filled('student_type') && in_array($request->student_type, ['new', 'old'], true)) {
            $type = $request->student_type;
            $query->whereHas('currentAcademicHistory', function ($q) use ($type, $academicYearId) {
                if ($academicYearId) {
                    $q->where('academic_year_id', $academicYearId);
                }
                $q->where('student_type', 'like', "%{$type}%");
            });
        }

        $students = $query->orderBy('first_name')->get();

        $headers = ['S.No', 'Admission No', 'Roll No', 'First Name', 'Last Name', 'Gender', 'Class', 'Section', 'Status', 'Father Name', 'Mother Name', 'Contact'];

        $rows = [];
        foreach ($students as $i => $s) {
            $history = $s->currentAcademicHistory;
            $rows[] = [
                $i + 1,
                $s->admission_no,
                $s->roll_no,
                $s->first_name,
                $s->last_name,
                ucfirst($s->gender ?? ''),
                $history?->courseClass?->name ?? '',
                $history?->section?->name ?? '',
                $s->status?->label() ?? '',
                $s->studentParent?->father_name ?? '',
                $s->studentParent?->mother_name ?? '',
                $s->studentParent?->primary_phone ?? '',
            ];
        }

        return $this->exportResponse($request, $headers, $rows, 'students-export-' . now()->format('Y-m-d'), [
            'orientation' => 'landscape',
        ]);
    }
}
