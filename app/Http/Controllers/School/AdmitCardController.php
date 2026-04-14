<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\Section;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Student;

class AdmitCardController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->can('manage_exam_terms')) abort(403);

        $schedules = ExamSchedule::with(['examType', 'courseClass', 'sections'])
            ->where('school_id', app('current_school_id'))
            ->where('academic_year_id', app('current_academic_year_id'))
            ->latest()
            ->get();

        return Inertia::render('School/Examinations/AdmitCards/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function generate(Request $request)
    {
        if (!$request->user()->can('manage_exam_terms')) abort(403);
        
        $request->validate([
            'exam_schedule_id' => 'required|exists:exam_schedules,id',
            'section_id'       => 'required|exists:sections,id',
        ]);

        $schedule = ExamSchedule::with([
            'examType',
            'courseClass',
            'scheduleSubjects' => function($q) {
                $q->where('is_enabled', true)->with('subject');
            }
        ])->where('school_id', app('current_school_id'))->findOrFail($request->exam_schedule_id);

        $students = Student::with('studentParent')
            ->where('school_id', app('current_school_id'))
            ->whereHas('academicHistories', function($query) use ($schedule, $request) {
                $query->where('academic_year_id', app('current_academic_year_id'))
                      ->where('class_id', $schedule->course_class_id)
                      ->where('section_id', $request->section_id);
            })
            ->where('status', 'active')
            ->orderBy('roll_no')
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'schedule' => $schedule,
            'students' => $students
        ]);
    }

    public function print(Request $request)
    {
        if (!$request->user()->can('manage_exam_terms')) abort(403);
        
        $request->validate([
            'exam_schedule_id' => 'required|exists:exam_schedules,id',
            'section_id'       => 'required|exists:sections,id',
            'student_ids'      => 'required|string', // comma-separated ids
        ]);

        $schedule = ExamSchedule::with([
            'examType',
            'courseClass',
            'scheduleSubjects' => function($q) {
                $q->where('is_enabled', true)->with('subject');
            }
        ])->where('school_id', app('current_school_id'))->findOrFail($request->exam_schedule_id);

        $studentIds = explode(',', $request->student_ids);

        $students = Student::with('studentParent')
            ->where('school_id', app('current_school_id'))
            ->whereIn('id', $studentIds)
            ->where('status', 'active')
            ->orderBy('roll_no')
            ->orderBy('first_name')
            ->get();

        return Inertia::render('School/Examinations/AdmitCards/Print', [
            'scheduleData'  => $schedule,
            'students'      => $students,
            'sectionData'   => Section::find($request->section_id),
        ]);
    }
}
