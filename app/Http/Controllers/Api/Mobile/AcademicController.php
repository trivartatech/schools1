<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\BookList;
use App\Models\ExamMark;
use App\Models\ExamSchedule;
use App\Models\Holiday;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\StudentDiary;
use App\Models\SyllabusTopic;
use App\Models\SyllabusStatus;
use App\Models\Timetable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AcademicController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve which student's data to serve.
     * For parents with multiple children, honour the X-Active-Student-Id header
     * or `student_id` query param. Always validates ownership.
     */
    private function resolveStudentId($user, ?Request $request = null): ?int
    {
        if ($user->isStudent()) {
            return $user->student?->id;
        }

        if ($user->isParent()) {
            $parent   = $user->studentParent;
            if (!$parent) return null;

            $children = $parent->students()->pluck('id');
            if ($children->isEmpty()) return null;

            // Check for explicit child selection
            $requested = $request?->header('X-Active-Student-Id')
                      ?? $request?->input('student_id');

            if ($requested && $children->contains((int)$requested)) {
                return (int)$requested;
            }

            // Default: first child
            return $children->first();
        }

        return null;
    }

    private function calculateGrade(float $obtained, float $max): string
    {
        if ($max <= 0) return '-';
        $pct = ($obtained / $max) * 100;
        if ($pct >= 90) return 'A+';
        if ($pct >= 80) return 'A';
        if ($pct >= 70) return 'B+';
        if ($pct >= 60) return 'B';
        if ($pct >= 50) return 'C';
        if ($pct >= 40) return 'D';
        return 'F';
    }

    // ── Homework / Assignments ─────────────────────────────────────────────────

    public function homework(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        if (!$yearId) return response()->json(['assignments' => []]);

        $history = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
            ->where('academic_year_id', $yearId)->first() : null;

        $query = \App\Models\Assignment::where('school_id', $school->id)
            ->where('academic_year_id', $yearId)
            ->with(['subject:id,name', 'teacher.user:id,name'])
            ->orderByDesc('due_date');

        // Scope to student's class/section
        if ($history) {
            $query->where('class_id', $history->class_id)
                  ->where('section_id', $history->section_id);
        }

        // Filter: upcoming / all
        if ($request->input('filter') === 'upcoming') {
            $query->where('due_date', '>=', now());
        }

        // Eager-load submissions for this student to avoid N+1
        if ($studentId) {
            $query->with(['submissions' => fn($q) => $q->where('student_id', $studentId)]);
        }

        $assignments = $query->get()->map(function ($a) use ($studentId) {
            $submission = $studentId ? $a->submissions->first() : null;

            $daysLeft = $a->due_date ? (int) now()->diffInDays($a->due_date, false) : 0;

            return [
                'id'           => $a->id,
                'title'        => $a->title,
                'description'  => $a->description,
                'subject'      => $a->subject?->name ?? '',
                'teacher'      => $a->teacher?->user?->name ?? '',
                'due_date'     => $a->due_date?->toDateString(),
                'max_marks'    => $a->max_marks,
                'days_left'    => $daysLeft,
                'is_overdue'   => $daysLeft < 0,
                'submitted'    => $submission !== null,
                'grade'        => $submission?->marks !== null ? (string) $submission->marks : null,
                'has_attachments' => !empty($a->attachments),
            ];
        });

        return response()->json(['assignments' => $assignments]);
    }

    public function submitHomework(Request $request, $id): JsonResponse
    {
        $user      = $request->user();
        $studentId = $this->resolveStudentId($user, $request);
        if (!$studentId) return response()->json(['message' => 'Student not found'], 404);

        $assignment = \App\Models\Assignment::findOrFail($id);

        $request->validate([
            'content' => 'nullable|string|max:5000',
        ]);

        $submission = \App\Models\AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $id, 'student_id' => $studentId],
            [
                'content'      => $request->input('content', ''),
                'submitted_at' => now(),
                'is_late'      => $assignment->due_date && now()->gt($assignment->due_date),
            ]
        );

        return response()->json(['success' => true, 'submitted' => true]);
    }

    // ── Syllabus ─────────────────────────────────────────────────────────────

    public function syllabus(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['data' => ['subjects' => []]]);
        }

        $history = StudentAcademicHistory::where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->latest()
            ->first();

        if (!$history) {
            return response()->json(['data' => ['subjects' => []]]);
        }

        // Get all syllabus topics for the student's class
        $topics = SyllabusTopic::where('school_id', $school->id)
            ->where('class_id', $history->class_id)
            ->with(['subject:id,name'])
            ->orderBy('subject_id')
            ->orderBy('sort_order')
            ->get();

        // Get completion statuses for the student's section
        $topicIds = $topics->pluck('id');
        $statuses = SyllabusStatus::whereIn('topic_id', $topicIds)
            ->where('section_id', $history->section_id)
            ->get()
            ->keyBy('topic_id');

        // Get subject teachers from ClassSubject for this class+section
        $subjectTeachers = \App\Models\ClassSubject::where('course_class_id', $history->class_id)
            ->where('section_id', $history->section_id)
            ->whereNotNull('incharge_staff_id')
            ->with('inchargeStaff.user:id,name')
            ->get()
            ->keyBy('subject_id');

        // Group by subject
        $subjects = $topics->groupBy('subject_id')->map(function ($subjectTopics) use ($statuses, $subjectTeachers) {
            $subject = $subjectTopics->first()?->subject;
            $topicList = $subjectTopics->map(function ($t) use ($statuses) {
                $status = $statuses->get($t->id);
                return [
                    'id'        => $t->id,
                    'name'      => $t->topic_name,
                    'chapter'   => $t->chapter_name,
                    'completed' => $status?->status === 'completed',
                ];
            });

            $total     = $topicList->count();
            $completed = $topicList->where('completed', true)->count();
            $teacher   = $subjectTeachers->get($subject?->id)?->inchargeStaff?->user?->name ?? '';

            return [
                'id'               => $subject?->id,
                'name'             => $subject?->name ?? 'Unknown',
                'teacher'          => $teacher,
                'topics'           => $topicList->values(),
                'total_topics'     => $total,
                'completed_topics' => $completed,
            ];
        })->values();

        return response()->json(['data' => ['subjects' => $subjects]]);
    }

    // ── Student Diary ────────────────────────────────────────────────────────

    public function diary(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['data' => []]);
        }

        $history = StudentAcademicHistory::where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->latest()
            ->first();

        if (!$history) {
            return response()->json(['data' => []]);
        }

        $date = $request->input('date', now()->toDateString());
        $page = $request->input('page', 1);

        $entries = StudentDiary::where('school_id', $school->id)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->where('class_id', $history->class_id)
            ->where('section_id', $history->section_id)
            ->whereDate('date', $date)
            ->with(['subject:id,name', 'teacher.user:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(20, ['*'], 'page', $page);

        // Get completion data for current student
        $diaryIds    = collect($entries->items())->pluck('id');
        $completions = $studentId
            ? \App\Models\DiaryCompletion::whereIn('diary_id', $diaryIds)
                ->where('student_id', $studentId)->pluck('diary_id')->toArray()
            : [];
        $completionCounts = \App\Models\DiaryCompletion::whereIn('diary_id', $diaryIds)
            ->selectRaw('diary_id, count(*) as cnt')
            ->groupBy('diary_id')->pluck('cnt', 'diary_id')->toArray();

        $data = collect($entries->items())->map(fn($d) => [
            'id'               => $d->id,
            'subject'          => $d->subject?->name ?? 'General',
            'teacher'          => $d->teacher?->user?->name ?? '',
            'type'             => $d->subject_id ? 'classwork' : 'notice',
            'content'          => $d->content,
            'attachments'      => $d->attachments ?? [],
            'completed'        => in_array($d->id, $completions),
            'completion_count' => $completionCounts[$d->id] ?? 0,
            'date'             => $d->date?->toDateString(),
            'created_at'       => $d->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data'         => $data,
            'current_page' => $entries->currentPage(),
            'last_page'    => $entries->lastPage(),
        ]);
    }

    public function toggleDiaryComplete(Request $request, $id): JsonResponse
    {
        $user      = $request->user();
        $studentId = $this->resolveStudentId($user, $request);
        if (!$studentId) return response()->json(['message' => 'Student not found'], 404);

        $diary = StudentDiary::findOrFail($id);

        // Check if already completed
        $existing = \App\Models\DiaryCompletion::where('diary_id', $id)
            ->where('student_id', $studentId)->first();

        if ($existing) {
            $existing->delete();
            $completed = false;
        } else {
            \App\Models\DiaryCompletion::create([
                'diary_id'   => $id,
                'student_id' => $studentId,
            ]);
            $completed = true;
        }

        $completionCount = \App\Models\DiaryCompletion::where('diary_id', $id)->count();

        return response()->json([
            'completed'        => $completed,
            'completion_count' => $completionCount,
        ]);
    }

    // ── Resources ─────────────────────────────────────────────────────────────

    public function resources(Request $request): JsonResponse
    {
        $school = app('current_school');

        // LearningMaterial may not exist in all setups — return empty gracefully
        if (!class_exists(\App\Models\LearningMaterial::class)) {
            return response()->json(['data' => []]);
        }

        $user      = $request->user();
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);
        $subjectId = $request->input('subject_id');
        $page      = $request->input('page', 1);

        $history = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->latest()
            ->first() : null;

        $query = \App\Models\LearningMaterial::where('school_id', $school->id)
            ->where('is_published', true)
            ->when($history, fn($q) => $q->where('class_id', $history->class_id))
            ->when($subjectId, fn($q) => $q->where('subject_id', $subjectId))
            ->with(['subject:id,name', 'teacher.user:id,name'])
            ->latest()
            ->paginate(20, ['*'], 'page', $page);

        $data = collect($query->items())->map(fn($r) => [
            'id'           => $r->id,
            'title'        => $r->title,
            'description'  => $r->description,
            'subject'      => $r->subject?->name ?? '',
            'teacher'      => $r->teacher?->user?->name ?? '',
            'type'         => strtolower($r->type ?? 'pdf'),
            'chapter_name' => $r->chapter_name,
            'file_url'     => $r->file_path ? asset('storage/' . $r->file_path) : null,
            'external_url' => $r->external_url,
            'created_at'   => $r->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data'         => $data,
            'current_page' => $query->currentPage(),
            'last_page'    => $query->lastPage(),
        ]);
    }

    // ── Library / Book List ──────────────────────────────────────────────────

    public function bookList(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);
        $search    = $request->input('search');
        $page      = $request->input('page', 1);

        // Get student's class
        $history = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->latest()
            ->first() : null;

        $query = BookList::where('school_id', $school->id)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->when($history, fn($q) => $q->where('class_id', $history->class_id))
            ->when($search, fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('book_name', 'like', "%{$search}%")
                   ->orWhere('author', 'like', "%{$search}%");
            }))
            ->with(['subject:id,name', 'courseClass:id,name'])
            ->orderBy('subject_id')
            ->paginate(30, ['*'], 'page', $page);

        $data = collect($query->items())->map(fn($b) => [
            'id'          => $b->id,
            'name'        => $b->book_name,
            'author'      => $b->author,
            'publisher'   => $b->publisher,
            'isbn'        => $b->isbn,
            'subject'     => $b->subject?->name ?? '',
            'class_name'  => $b->courseClass?->name ?? '',
            'is_required' => true,
        ]);

        return response()->json([
            'data'         => $data,
            'current_page' => $query->currentPage(),
            'last_page'    => $query->lastPage(),
        ]);
    }

    // ── Holidays ─────────────────────────────────────────────────────────────

    public function holidays(Request $request): JsonResponse
    {
        $school = app('current_school');

        $holidays = Holiday::where('school_id', $school->id)
            ->orderBy('date')
            ->get()
            ->map(fn($h) => [
                'id'          => $h->id,
                'name'        => $h->title,
                'date'        => $h->date?->toDateString(),
                'end_date'    => $h->end_date?->toDateString(),
                'type'        => $h->type ?? 'holiday',
                'description' => $h->description,
            ]);

        return response()->json(['data' => $holidays]);
    }

    // ── Exams ─────────────────────────────────────────────────────────────────

    public function exams(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);
        $history   = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
            ->where('academic_year_id', $yearId)->first() : null;

        $schedules = collect();
        if ($history) {
            $schedules = ExamSchedule::where('school_id', $school->id)
                ->where('academic_year_id', $yearId)
                ->where('course_class_id', $history->class_id)
                ->where('status', 'published')
                ->with(['examType:id,name,code', 'scheduleSubjects.subject:id,name'])
                ->orderByDesc('created_at')
                ->get();
        }

        return response()->json(['schedules' => $schedules]);
    }

    // ── Results / Report Card ─────────────────────────────────────────────────

    public function results(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId || !$yearId) {
            return response()->json(['schedules' => []]);
        }

        $history = StudentAcademicHistory::where('student_id', $studentId)
            ->where('academic_year_id', $yearId)
            ->first();

        if (!$history) {
            return response()->json(['schedules' => []]);
        }

        // Published exam schedules for this student's class
        $schedules = \App\Models\ExamSchedule::where('school_id', $school->id)
            ->where('academic_year_id', $yearId)
            ->where('course_class_id', $history->class_id)
            ->where('status', 'published')
            ->with(['examType:id,name,code'])
            ->get();

        // For each schedule, attach the student's marks
        $result = $schedules->map(function ($schedule) use ($studentId) {
            $subjects = \App\Models\ExamScheduleSubject::where('exam_schedule_id', $schedule->id)
                ->where('is_enabled', true)
                ->with(['subject:id,name', 'examAssessment.items'])
                ->get();

            $subjectResults = $subjects->map(function ($ss) use ($studentId) {
                $marks = \App\Models\ExamMark::where('student_id', $studentId)
                    ->where('exam_schedule_subject_id', $ss->id)
                    ->get(['marks_obtained', 'is_absent', 'exam_assessment_item_id']);

                $totalObtained = $marks->where('is_absent', false)->sum('marks_obtained');
                $isAbsent      = $marks->every(fn($m) => $m->is_absent);
                $maxMarks      = $ss->examAssessment?->items->sum('max_marks') ?? $ss->max_marks ?? 0;

                return [
                    'subject_name'   => $ss->subject->name ?? 'Unknown',
                    'max_marks'      => $maxMarks,
                    'marks_obtained' => $isAbsent ? null : $totalObtained,
                    'is_absent'      => $isAbsent,
                    'percentage'     => ($maxMarks > 0 && !$isAbsent)
                        ? round($totalObtained / $maxMarks * 100, 1) : null,
                ];
            });

            $totalMax     = $subjectResults->whereNotNull('max_marks')->sum('max_marks');
            $totalObtained= $subjectResults->whereNotNull('marks_obtained')->sum('marks_obtained');

            return [
                'schedule_id'   => $schedule->id,
                'exam_name'     => $schedule->examType->name ?? 'Exam',
                'exam_code'     => $schedule->examType->code ?? '',
                'subjects'      => $subjectResults,
                'total_max'     => $totalMax,
                'total_obtained'=> $totalObtained,
                'percentage'    => $totalMax > 0 ? round($totalObtained / $totalMax * 100, 1) : null,
            ];
        });

        return response()->json(['schedules' => $result]);
    }

    // ── Report Cards ─────────────────────────────────────────────────────────

    public function reportCards(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['data' => []]);
        }

        $history = StudentAcademicHistory::where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->latest()
            ->first();

        if (!$history) {
            return response()->json(['data' => []]);
        }

        // Get published exam schedules for this class
        $schedules = ExamSchedule::where('school_id', $school->id)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->where('course_class_id', $history->class_id)
            ->where('status', 'published')
            ->with(['examType:id,name,code', 'academicYear:id,name', 'scheduleSubjects.subject:id,name'])
            ->latest()
            ->get();

        $reportCards = $schedules->map(function ($schedule) use ($studentId) {
            $subjects = [];
            $totalObtained = 0;
            $totalMax = 0;

            foreach ($schedule->scheduleSubjects as $ss) {
                $marks = ExamMark::where('student_id', $studentId)
                    ->where('exam_schedule_subject_id', $ss->id)
                    ->get();

                $obtained = $marks->sum('marks_obtained');
                $maxMarks = $ss->max_marks ?? $marks->count() * 100;

                $totalObtained += $obtained;
                $totalMax += $maxMarks;

                $subjects[] = [
                    'name'           => $ss->subject?->name ?? 'Unknown',
                    'marks_obtained' => round($obtained, 1),
                    'max_marks'      => $maxMarks,
                    'grade'          => $this->calculateGrade($obtained, $maxMarks),
                    'is_absent'      => $marks->contains('is_absent', true),
                ];
            }

            $percentage = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 1) : 0;

            return [
                'id'                  => $schedule->id,
                'term_name'           => $schedule->examType?->name ?? 'Exam',
                'academic_year'       => $schedule->academicYear?->name ?? '',
                'issued_date'         => $schedule->updated_at?->toDateString(),
                'overall_percentage'  => $percentage,
                'overall_grade'       => $this->calculateGrade($totalObtained, $totalMax),
                'rank'                => null,
                'status'              => 'Published',
                'subjects'            => $subjects,
                'total_obtained'      => round($totalObtained, 1),
                'total_max'           => $totalMax,
                'teacher_remarks'     => null,
                'principal_remarks'   => null,
            ];
        });

        return response()->json(['data' => $reportCards]);
    }

    // ── Report Card Download ─────────────────────────────────────────────────

    public function downloadReportCard(Request $request, int $scheduleId): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['message' => 'No student linked.'], 422);
        }

        $schedule = ExamSchedule::where('school_id', $school->id)
            ->where('id', $scheduleId)
            ->where('status', 'published')
            ->with(['examType:id,name,code', 'academicYear:id,name', 'scheduleSubjects.subject:id,name'])
            ->firstOrFail();

        $student = Student::with('user:id,name')->find($studentId);

        $history = StudentAcademicHistory::where('student_id', $studentId)
            ->where('academic_year_id', $schedule->academic_year_id)
            ->first();

        $subjects = [];
        $totalObtained = 0;
        $totalMax = 0;

        foreach ($schedule->scheduleSubjects as $ss) {
            $marks = ExamMark::where('student_id', $studentId)
                ->where('exam_schedule_subject_id', $ss->id)
                ->get();

            $obtained = $marks->sum('marks_obtained');
            $maxMarks = $ss->max_marks ?? $marks->count() * 100;
            $totalObtained += $obtained;
            $totalMax += $maxMarks;

            $subjects[] = [
                'name'           => $ss->subject?->name ?? 'Unknown',
                'marks_obtained' => round($obtained, 1),
                'max_marks'      => $maxMarks,
                'grade'          => $this->calculateGrade($obtained, $maxMarks),
                'percentage'     => $maxMarks > 0 ? round(($obtained / $maxMarks) * 100, 1) : 0,
                'is_absent'      => $marks->contains('is_absent', true),
            ];
        }

        $percentage = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 1) : 0;

        return response()->json([
            'student'      => [
                'name'        => $student?->user?->name ?? 'Student',
                'admission_no' => $student?->admission_no,
                'class'       => $history?->class_name ?? '',
                'section'     => $history?->section_name ?? '',
                'roll_no'     => $history?->roll_number,
            ],
            'exam'         => [
                'name'          => $schedule->examType?->name ?? 'Exam',
                'academic_year' => $schedule->academicYear?->name ?? '',
                'date'          => $schedule->updated_at?->toDateString(),
            ],
            'school'       => [
                'name'    => $school->name,
                'address' => $school->address ?? '',
                'logo'    => $school->logo_url ?? null,
            ],
            'subjects'            => $subjects,
            'total_obtained'      => round($totalObtained, 1),
            'total_max'           => $totalMax,
            'overall_percentage'  => $percentage,
            'overall_grade'       => $this->calculateGrade($totalObtained, $totalMax),
        ]);
    }

    // ── Timetable ─────────────────────────────────────────────────────────────

    public function timetable(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $schedule  = [];

        if ($user->isTeacher() && $user->staff) {
            $items = Timetable::where('school_id', $school->id)
                ->where('academic_year_id', $yearId)
                ->where('teacher_id', $user->staff->id)
                ->with(['subject', 'courseClass', 'section', 'period'])
                ->get();

            foreach ($items as $item) {
                $schedule[$item->day_of_week][] = [
                    'period_name'  => $item->period->name        ?? "Period {$item->period_id}",
                    'subject_name' => $item->subject->name       ?? 'Unknown',
                    'class_name'   => $item->courseClass->name   ?? '',
                    'section_name' => $item->section->name       ?? '',
                    'start_time'   => $item->period->start_time  ?? '',
                    'end_time'     => $item->period->end_time    ?? '',
                    'room'         => $item->room                ?? '',
                    'is_break'     => false,
                ];
            }
        } else {
            $studentId = $this->resolveStudentId($user, $request);
            $history   = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
                ->where('academic_year_id', $yearId)->first() : null;

            if ($history) {
                $items = Timetable::where('school_id', $school->id)
                    ->where('academic_year_id', $yearId)
                    ->where('class_id', $history->class_id)
                    ->where('section_id', $history->section_id)
                    ->with(['subject', 'teacher.user:id,name', 'period'])
                    ->get();

                foreach ($items as $item) {
                    $schedule[$item->day_of_week][] = [
                        'period_name'  => $item->period->name           ?? "Period {$item->period_id}",
                        'subject_name' => $item->subject->name          ?? 'Unknown',
                        'teacher_name' => $item->teacher->user->name    ?? '',
                        'start_time'   => $item->period->start_time     ?? '',
                        'end_time'     => $item->period->end_time       ?? '',
                        'room'         => $item->room                   ?? '',
                        'is_break'     => false,
                    ];
                }
            }
        }

        foreach ($schedule as &$day) {
            usort($day, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));
        }

        return response()->json(['schedule' => $schedule]);
    }

    // ── Student ID Card ──────────────────────────────────────────────────────

    public function idCard(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        $student = Student::with([
            'currentAcademicHistory.courseClass',
            'currentAcademicHistory.section',
            'studentParent',
            'user:id,name,email,phone',
        ])->find($studentId);

        if (!$student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        $history = $student->currentAcademicHistory;
        $parent  = $student->studentParent;

        return response()->json([
            'data' => [
                'student_id'    => $student->id,
                'name'          => $student->name,
                'photo_url'     => $student->photo_url,
                'admission_no'  => $student->admission_no,
                'roll_no'       => $student->roll_no,
                'dob'           => $student->dob,
                'gender'        => $student->gender,
                'blood_group'   => $student->blood_group,
                'class'         => $history?->courseClass?->name ?? '',
                'section'       => $history?->section?->name ?? '',
                'academic_year' => $history?->academicYear?->name ?? '',
                'parent_name'   => $parent?->father_name ?? $parent?->mother_name ?? $user->name,
                'contact'       => $user->phone ?? $parent?->phone ?? '',
                'email'         => $user->email ?? '',
                'address'       => $student->address ?? $parent?->address ?? '',
                'school' => [
                    'name'    => $school->name,
                    'logo'    => $school->logo ? asset('storage/' . $school->logo) : null,
                    'address' => $school->settings['address_line1'] ?? '',
                    'phone'   => $school->settings['phone'] ?? '',
                ],
            ],
        ]);
    }
}
