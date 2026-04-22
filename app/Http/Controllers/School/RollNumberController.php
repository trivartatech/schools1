<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseClass;
use App\Models\Section;
use App\Models\StudentAcademicHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RollNumberController extends Controller
{
    // ── Index ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        $schoolId      = app('current_school_id');
        $currentYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $academicYears = AcademicYear::where('school_id', $schoolId)
            ->orderByDesc('start_date')->get(['id', 'name', 'is_current']);

        $classes = CourseClass::where('school_id', $schoolId)
            ->orderBy('numeric_value')->get(['id', 'name']);

        // Default filter values
        $yearId    = $request->integer('academic_year_id', $currentYearId);
        $classId   = $request->integer('class_id', 0);
        $sectionId = $request->integer('section_id', 0);

        // Load sections for selected class
        $sections = $classId
            ? Section::where('school_id', $schoolId)->where('course_class_id', $classId)
                ->forYear($yearId)
                ->orderBy('name')->get(['id', 'name'])
            : collect();

        // Load students for selected class+section+year
        $students = [];
        if ($classId && $yearId) {
            $query = StudentAcademicHistory::with(['student:id,first_name,last_name,admission_no,photo,gender'])
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $yearId)
                ->where('class_id', $classId);

            if ($sectionId) {
                $query->where('section_id', $sectionId);
            }

            $students = $query->get()->filter(fn($h) => $h->student !== null)->map(function ($h) {
                return [
                    'history_id'   => $h->id,
                    'student_id'   => $h->student_id,
                    'name'         => trim($h->student->first_name . ' ' . $h->student->last_name),
                    'first_name'   => $h->student->first_name,
                    'last_name'    => $h->student->last_name,
                    'admission_no' => $h->student->admission_no,
                    'gender'       => $h->student->gender,
                    'photo_url'    => $h->student->photo_url,
                    'roll_no'      => $h->roll_no ?? $h->student->roll_no ?? '',
                ];
            })->sortBy(fn($s) => (int) $s['roll_no'] ?: PHP_INT_MAX)->values()->all();
        }

        return Inertia::render('School/Students/RollNumbers/Index', [
            'academicYears' => $academicYears,
            'classes'       => $classes,
            'sections'      => $sections,
            'students'      => $students,
            'filters'       => [
                'academic_year_id' => $yearId,
                'class_id'         => $classId,
                'section_id'       => $sectionId,
            ],
        ]);
    }

    // ── Save (bulk update) ────────────────────────────────────

    public function save(Request $request)
    {
        $schoolId = app('current_school_id');

        $request->validate([
            'academic_year_id'          => ['required', 'exists:academic_years,id'],
            'class_id'                  => ['required', 'exists:course_classes,id'],
            'section_id'                => ['nullable', 'exists:sections,id'],
            'assignments'               => ['required', 'array', 'min:1'],
            'assignments.*.history_id'  => ['required', 'integer'],
            'assignments.*.student_id'  => ['required', 'integer'],
            'assignments.*.roll_no'     => ['nullable', 'string', 'max:20'],
        ]);

        $yearId    = $request->academic_year_id;
        $classId   = $request->class_id;
        $sectionId = $request->section_id;

        // Detect duplicate roll numbers (excluding blanks)
        $rollNos = collect($request->assignments)
            ->pluck('roll_no')
            ->filter()
            ->map(fn($r) => trim($r));

        if ($rollNos->count() !== $rollNos->unique()->count()) {
            return back()->withErrors(['assignments' => 'Duplicate roll numbers detected. Each roll number must be unique within the class/section.']);
        }

        DB::transaction(function () use ($request, $schoolId, $yearId, $classId, $sectionId) {
            foreach ($request->assignments as $row) {
                $rollNo = $row['roll_no'] ? trim($row['roll_no']) : null;

                // Update student_academic_histories.roll_no (per-year canonical source)
                StudentAcademicHistory::where('id', $row['history_id'])
                    ->where('school_id', $schoolId)      // tenant guard
                    ->where('academic_year_id', $yearId) // extra safety
                    ->update(['roll_no' => $rollNo]);

                // Sync to students.roll_no if this is the current academic year
                $currentYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
                if ($currentYearId && $yearId == $currentYearId) {
                    \App\Models\Student::where('id', $row['student_id'])
                        ->where('school_id', $schoolId)
                        ->update(['roll_no' => $rollNo]);
                }
            }
        });

        return back()->with('success', 'Roll numbers saved successfully.');
    }
}
