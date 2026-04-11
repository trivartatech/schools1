<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Services\TeacherScopeService;
use Illuminate\Http\Request;

class ExamMarkController extends Controller
{
    /**
     * Display the marks entry UI with role-based filtering.
     */
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $user = $request->user();

        // \u2500\u2500 Teacher scope (handles class/section/subject hierarchy) \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
        $scope = app(TeacherScopeService::class)->for($user);

        $schedules = \App\Models\ExamSchedule::with(['examType', 'courseClass'])
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', 'published')
            ->get();

        // Filter schedules to only classes teacher is incharge of
        if ($scope->restricted && $scope->classIds->isNotEmpty()) {
            $schedules = $schedules->whereIn('course_class_id', $scope->classIds->toArray())->values();
        }

        return \Inertia\Inertia::render('School/Examinations/Marks/Index', [
            'schedules'        => $schedules,
            'allowedMap'       => $scope->restricted ? $scope->allowedMap : null,
            'sections'         => \App\Models\Section::with('courseClass:id,name')
                ->where('school_id', $schoolId)
                ->get(['id', 'course_class_id', 'name']),
            'scheduleSubjects' => \App\Models\ExamScheduleSubject::with(['subject', 'examAssessment.items'])
                ->whereIn('exam_schedule_id', $schedules->pluck('id'))
                ->get(),
        ]);
    }

    /**
     * Fetch students and their current marks for the selected schedule, section, and subject.
     */
    public function students(Request $request)
    {
        $request->validate([
            'exam_schedule_id' => 'required|exists:exam_schedules,id',
            'section_id' => 'required|exists:sections,id',
            'exam_schedule_subject_id' => 'required|exists:exam_schedule_subjects,id',
        ]);

        $scheduleSubject = \App\Models\ExamScheduleSubject::with(['examAssessment.items', 'markConfigs'])->findOrFail($request->exam_schedule_subject_id);

        // Tenant guard — prevent cross-school access via spoofed IDs
        $user = auth()->user();
        $scope = app(TeacherScopeService::class)->for($user);
        $schedule = \App\Models\ExamSchedule::where('school_id', app('current_school_id'))->findOrFail($request->exam_schedule_id);

        if ($scope->restricted) {
            if (!$scope->classIds->contains($schedule->course_class_id)) {
                abort(403, 'Unauthorized access to this class.');
            }
            if ($scope->allowedMap) {
                $allowedSections = array_keys($scope->allowedMap[$schedule->course_class_id] ?? []);
                if (!in_array($request->section_id, $allowedSections)) {
                    abort(403, 'Unauthorized access to this section.');
                }
                $allowedSubjects = $scope->allowedMap[$schedule->course_class_id][$request->section_id] ?? [];
                if (!in_array($scheduleSubject->subject_id, $allowedSubjects)) {
                    abort(403, 'Unauthorized access to this subject.');
                }
            }
        }

        $academicYearId = app('current_academic_year_id');

        $students = \App\Models\Student::with(['user:id,name'])
            ->whereHas('academicHistories', function($query) use ($request, $academicYearId) {
                $query->where('section_id', $request->section_id)
                      ->where('academic_year_id', $academicYearId);
            })
            ->where('status', 'active')
            ->get()
            ->sortBy(function($student) {
                $hist = $student->academicHistories->first();
                return [is_null($hist?->roll_no) ? PHP_INT_MAX : (int)$hist->roll_no, $student->id];
            })->values();

        $existingMarks = \App\Models\ExamMark::whereIn('student_id', $students->pluck('id'))
            ->where('exam_schedule_subject_id', $scheduleSubject->id)
            ->get();

        // Format to easily map in Vue
        $marksMap = [];
        foreach ($existingMarks as $m) {
            $marksMap[$m->student_id][$m->exam_assessment_item_id] = [
                'marks_obtained' => $m->marks_obtained,
                'is_absent' => $m->is_absent,
                'teacher_remarks' => $m->teacher_remarks,
            ];
        }

        $assessmentItems = collect();
        if ($scheduleSubject->examAssessment) {
            $assessmentItems = $scheduleSubject->examAssessment->items->map(function ($item) use ($scheduleSubject) {
                $sm = $scheduleSubject->markConfigs->firstWhere('exam_assessment_item_id', $item->id);
                if ($sm) {
                    $item->max_marks = $sm->max_marks;
                    $item->passing_marks = $sm->passing_marks;
                }
                return $item;
            });
        }

        return response()->json([
            'students' => $students,
            'marksMap' => $marksMap,
            'assessmentItems' => $assessmentItems,
        ]);
    }

    /**
     * Store the submitted marks.
     */
    public function store(Request $request)
    {
        $request->validate([
            'exam_schedule_id' => 'required',
            'section_id' => 'required',
            'exam_schedule_subject_id' => 'required',
            'marks' => 'required|array', // Structure: marks[student_id][assessment_item_id] = {marks_obtained, is_absent, teacher_remarks}
        ]);

        $schoolId = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        
        $scheduleSubject = \App\Models\ExamScheduleSubject::with(['examAssessment.items', 'markConfigs'])->findOrFail($request->exam_schedule_subject_id);

        // RBAC + tenant guard
        $user = auth()->user();
        $scope = app(TeacherScopeService::class)->for($user);
        $schedule = \App\Models\ExamSchedule::where('school_id', $schoolId)->findOrFail($request->exam_schedule_id);

        if ($scope->restricted) {
            if (!$scope->classIds->contains($schedule->course_class_id)) {
                abort(403, 'Unauthorized access to this class.');
            }
            if ($scope->allowedMap) {
                $allowedSections = array_keys($scope->allowedMap[$schedule->course_class_id] ?? []);
                if (!in_array($request->section_id, $allowedSections)) {
                    abort(403, 'Unauthorized access to this section.');
                }
                $allowedSubjects = $scope->allowedMap[$schedule->course_class_id][$request->section_id] ?? [];
                if (!in_array($scheduleSubject->subject_id, $allowedSubjects)) {
                    abort(403, 'Unauthorized access to this subject.');
                }
            }
        }

        $maxMarksMap = [];
        if ($scheduleSubject->examAssessment) {
            foreach ($scheduleSubject->examAssessment->items as $item) {
                $sm = $scheduleSubject->markConfigs->firstWhere('exam_assessment_item_id', $item->id);
                // Use per-subject mark config override if set, otherwise fall back to item default
                $maxMarksMap[$item->id] = ($sm && isset($sm->max_marks))
                    ? (float) $sm->max_marks
                    : (float) ($item->max_marks ?? 0);
            }
        }

        // Validate max marks before saving anything
        foreach ($request->marks as $studentId => $items) {
            foreach ($items as $itemId => $data) {
                if (empty($data['is_absent'])) {
                    $max = $maxMarksMap[$itemId] ?? 0;
                    $obtained = (float)($data['marks_obtained'] ?? 0);
                    if ($max > 0 && $obtained > $max) {
                        return redirect()->back()->withErrors(['marks' => "Marks entered exceed the maximum marks allowed ({$max}) for an assessment item."]);
                    }
                    if ($obtained < 0) {
                        return redirect()->back()->withErrors(['marks' => "Marks cannot be negative."]);
                    }
                }
            }
        }

        // Upsert markConfigs
        foreach ($request->marks as $studentId => $items) {
            foreach ($items as $itemId => $data) {
                \App\Models\ExamMark::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'exam_schedule_subject_id' => $request->exam_schedule_subject_id,
                        'exam_assessment_item_id' => $itemId,
                    ],
                    [
                        'school_id' => $schoolId,
                        'academic_year_id' => $academicYearId,
                        'marks_obtained' => !empty($data['is_absent']) ? null : ($data['marks_obtained'] ?? 0),
                        'is_absent' => !empty($data['is_absent']),
                        'teacher_remarks' => $data['teacher_remarks'] ?? null,
                    ]
                );
            }
        }

        return redirect()->back()->with('success', 'Marks saved successfully.');
    }
}
