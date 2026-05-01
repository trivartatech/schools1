<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ExamMark;
use App\Models\ExamSchedule;
use App\Models\ExamScheduleSubject;
use App\Models\Section;
use App\Models\Student;
use App\Services\TeacherScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamMarkController extends Controller
{
    /**
     * Get published exam schedules accessible to the teacher/admin,
     * with sections and subjects embedded.
     *
     * For admins (unrestricted): returns all published schedules across all
     * academic years so a mismatched `is_current` year never hides schedules.
     * For teachers (restricted): filters to the current academic year only.
     */
    public function schedules(Request $request): JsonResponse
    {
        try {
            $schoolId       = app()->bound('current_school_id') ? app('current_school_id') : null;
            $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

            if (! $schoolId) {
                return response()->json(['message' => 'School context could not be resolved. Check tenant configuration.'], 422);
            }

            $user  = $request->user();
            $scope = app(TeacherScopeService::class)->for($user);

            $query = ExamSchedule::with(['examType', 'courseClass', 'academicYear'])
                ->where('school_id', $schoolId)
                ->where('status', 'published');

            // Teachers only see the current academic year; admins see all years
            if ($scope->restricted && $academicYearId) {
                $query->where('academic_year_id', $academicYearId);
            }

            $schedules = $query->orderBy('academic_year_id', 'desc')->get();

            if ($scope->restricted && $scope->classIds->isNotEmpty()) {
                $schedules = $schedules->whereIn('course_class_id', $scope->classIds->toArray())->values();
            }

            // Convert to plain arrays for query builder compatibility
            $classIds    = $schedules->pluck('course_class_id')->unique()->values()->toArray();
            $scheduleIds = $schedules->pluck('id')->toArray();

            $sections = $classIds
                ? Section::whereIn('course_class_id', $classIds)
                    ->where('school_id', $schoolId)
                    ->forCurrentYear()
                    ->get(['id', 'course_class_id', 'name'])
                : collect();

            $scheduleSubjects = $scheduleIds
                ? ExamScheduleSubject::with(['subject', 'examAssessment.items', 'markConfigs'])
                    ->whereIn('exam_schedule_id', $scheduleIds)
                    ->where('is_enabled', true)
                    ->get()
                : collect();

            $result = $schedules->map(function ($schedule) use ($sections, $scheduleSubjects, $scope) {
                $classSections = $sections->where('course_class_id', $schedule->course_class_id)->values();

                if ($scope->restricted && $scope->allowedMap) {
                    $allowedSectionIds = array_keys($scope->allowedMap[$schedule->course_class_id] ?? []);
                    $classSections     = $classSections->whereIn('id', $allowedSectionIds)->values();
                }

                $subjects = $scheduleSubjects
                    ->where('exam_schedule_id', $schedule->id)
                    ->map(function ($ss) {
                        $items = collect();
                        if ($ss->examAssessment) {
                            $items = $ss->examAssessment->items->map(function ($item) use ($ss) {
                                $config = $ss->markConfigs->firstWhere('exam_assessment_item_id', $item->id);
                                return [
                                    'id'            => $item->id,
                                    'name'          => $item->name,
                                    'max_marks'     => (float) ($config?->max_marks ?? $item->max_marks ?? 0),
                                    'passing_marks' => $config?->passing_marks ? (float) $config->passing_marks : null,
                                ];
                            });
                        }
                        return [
                            'id'               => $ss->id,
                            'subject_id'       => $ss->subject_id,
                            'subject_name'     => $ss->subject?->name ?? 'Unknown',
                            'assessment_items' => $items->values(),
                        ];
                    })->values();

                return [
                    'id'            => $schedule->id,
                    'name'          => $schedule->examType?->name ?? 'Exam',
                    'class_id'      => $schedule->course_class_id,
                    'class_name'    => $schedule->courseClass?->name ?? '',
                    'academic_year' => $schedule->academicYear?->name ?? null,
                    'sections'      => $classSections->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
                    'subjects'      => $subjects,
                ];
            });

            return response()->json(['schedules' => $result]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'file'    => basename($e->getFile()) . ':' . $e->getLine(),
                'class'   => get_class($e),
            ], 500);
        }
    }

    /**
     * Get students and their existing marks for a specific schedule/section/subject.
     */
    public function students(Request $request): JsonResponse
    {
        $request->validate([
            'exam_schedule_id'         => 'required|exists:exam_schedules,id',
            'section_id'               => 'required|exists:sections,id',
            'exam_schedule_subject_id' => 'required|exists:exam_schedule_subjects,id',
        ]);

        $schoolId = app('current_school_id');
        $user     = $request->user();

        $scope    = app(TeacherScopeService::class)->for($user);
        $schedule = ExamSchedule::where('school_id', $schoolId)->findOrFail($request->exam_schedule_id);

        // Use the schedule's own academic year (not the session-bound one)
        $academicYearId = $schedule->academic_year_id;

        if ($scope->restricted) {
            if (!$scope->classIds->contains($schedule->course_class_id)) {
                return response()->json(['error' => 'Unauthorized access to this class.'], 403);
            }
            if ($scope->allowedMap) {
                $allowedSections = array_keys($scope->allowedMap[$schedule->course_class_id] ?? []);
                if (!in_array((int) $request->section_id, $allowedSections)) {
                    return response()->json(['error' => 'Unauthorized access to this section.'], 403);
                }
            }
        }

        $sectionId       = $request->section_id;
        $scheduleSubject = ExamScheduleSubject::with(['examAssessment.items', 'markConfigs'])
            ->findOrFail($request->exam_schedule_subject_id);

        $students = Student::with([
            'academicHistories' => fn ($q) => $q->where('section_id', $sectionId)
                ->where('academic_year_id', $academicYearId),
        ])
            ->whereHas('academicHistories', function ($q) use ($sectionId, $academicYearId) {
                $q->where('section_id', $sectionId)->where('academic_year_id', $academicYearId);
            })
            ->where('status', 'active')
            ->get()
            ->sortBy(function ($student) {
                $hist = $student->academicHistories->first();
                return [is_null($hist?->roll_no) ? PHP_INT_MAX : (int) $hist->roll_no, $student->id];
            })->values();

        $existingMarks = ExamMark::whereIn('student_id', $students->pluck('id'))
            ->where('exam_schedule_subject_id', $scheduleSubject->id)
            ->get();

        $marksMap = [];
        foreach ($existingMarks as $m) {
            $marksMap[$m->student_id][$m->exam_assessment_item_id] = [
                'marks_obtained'  => $m->marks_obtained,
                'is_absent'       => (bool) $m->is_absent,
                'teacher_remarks' => $m->teacher_remarks,
            ];
        }

        $assessmentItems = collect();
        if ($scheduleSubject->examAssessment) {
            $assessmentItems = $scheduleSubject->examAssessment->items->map(function ($item) use ($scheduleSubject) {
                $config = $scheduleSubject->markConfigs->firstWhere('exam_assessment_item_id', $item->id);
                return [
                    'id'            => $item->id,
                    'name'          => $item->name,
                    'max_marks'     => (float) ($config?->max_marks ?? $item->max_marks ?? 0),
                    'passing_marks' => $config?->passing_marks ? (float) $config->passing_marks : null,
                ];
            });
        }

        $studentsData = $students->map(function ($s) {
            $hist = $s->academicHistories->first();
            $name = trim("{$s->first_name} {$s->last_name}");
            return [
                'id'        => $s->id,
                'name'      => $name !== '' ? $name : 'Unknown',
                'roll_no'   => $hist?->roll_no,
                'photo_url' => $s->photo_url,
            ];
        });

        return response()->json([
            'students'         => $studentsData,
            'marks_map'        => $marksMap,
            'assessment_items' => $assessmentItems->values(),
        ]);
    }

    /**
     * Save marks submitted from mobile.
     */
    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'exam_schedule_id'         => 'required|exists:exam_schedules,id',
            'section_id'               => 'required|exists:sections,id',
            'exam_schedule_subject_id' => 'required|exists:exam_schedule_subjects,id',
            'marks'                    => 'required|array',
        ]);

        $schoolId = app('current_school_id');
        $user     = $request->user();

        $scope    = app(TeacherScopeService::class)->for($user);
        $schedule = ExamSchedule::where('school_id', $schoolId)->findOrFail($request->exam_schedule_id);

        // Use the schedule's own academic year so marks are saved correctly
        // even when the server's `is_current` year differs from the schedule's year
        $academicYearId = $schedule->academic_year_id;

        if ($scope->restricted) {
            if (!$scope->classIds->contains($schedule->course_class_id)) {
                return response()->json(['error' => 'Unauthorized access to this class.'], 403);
            }
            if ($scope->allowedMap) {
                $allowedSections = array_keys($scope->allowedMap[$schedule->course_class_id] ?? []);
                if (!in_array((int) $request->section_id, $allowedSections)) {
                    return response()->json(['error' => 'Unauthorized access to this section.'], 403);
                }
                $scheduleSubjectCheck = ExamScheduleSubject::findOrFail($request->exam_schedule_subject_id);
                $allowedSubjects      = $scope->allowedMap[$schedule->course_class_id][$request->section_id] ?? [];
                if (!in_array($scheduleSubjectCheck->subject_id, $allowedSubjects)) {
                    return response()->json(['error' => 'Unauthorized access to this subject.'], 403);
                }
            }
        }

        $scheduleSubject = ExamScheduleSubject::with(['examAssessment.items', 'markConfigs'])
            ->findOrFail($request->exam_schedule_subject_id);

        // Build max marks map for validation
        $maxMarksMap = [];
        if ($scheduleSubject->examAssessment) {
            foreach ($scheduleSubject->examAssessment->items as $item) {
                $config              = $scheduleSubject->markConfigs->firstWhere('exam_assessment_item_id', $item->id);
                $maxMarksMap[$item->id] = ($config && isset($config->max_marks))
                    ? (float) $config->max_marks
                    : (float) ($item->max_marks ?? 0);
            }
        }

        foreach ($request->marks as $studentId => $items) {
            foreach ($items as $itemId => $data) {
                if (empty($data['is_absent'])) {
                    $max      = $maxMarksMap[$itemId] ?? 0;
                    $obtained = (float) ($data['marks_obtained'] ?? 0);
                    if ($max > 0 && $obtained > $max) {
                        return response()->json(['error' => "Marks exceed maximum ({$max}) for an assessment item."], 422);
                    }
                    if ($obtained < 0) {
                        return response()->json(['error' => 'Marks cannot be negative.'], 422);
                    }
                }
            }
        }

        foreach ($request->marks as $studentId => $items) {
            foreach ($items as $itemId => $data) {
                ExamMark::updateOrCreate(
                    [
                        'student_id'               => $studentId,
                        'exam_schedule_subject_id' => $request->exam_schedule_subject_id,
                        'exam_assessment_item_id'  => $itemId,
                    ],
                    [
                        'school_id'        => $schoolId,
                        'academic_year_id' => $academicYearId,
                        'marks_obtained'   => !empty($data['is_absent']) ? null : ($data['marks_obtained'] ?? 0),
                        'is_absent'        => !empty($data['is_absent']),
                        'teacher_remarks'  => $data['teacher_remarks'] ?? null,
                    ]
                );
            }
        }

        return response()->json(['message' => 'Marks saved successfully.']);
    }
}
