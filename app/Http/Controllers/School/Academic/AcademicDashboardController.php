<?php

namespace App\Http\Controllers\School\Academic;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\OnlineClass;
use App\Models\StudentDiary;
use App\Models\SyllabusTopic;
use App\Models\SyllabusStatus;
use App\Models\CourseClass;
use App\Services\TeacherScopeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AcademicDashboardController extends Controller
{
    /**
     * P5: Teacher Academic Dashboard — widgets for pending grading,
     * syllabus %, today's classes, recent diary entries.
     */
    public function index(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $scope          = app(TeacherScopeService::class)->for(auth()->user());

        // ── Pending Grading ────────────────────────────────────────────────
        $assignmentQuery = Assignment::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', 'published');

        if ($scope->restricted) {
            $assignmentQuery->whereIn('section_id', $scope->sectionIds->isEmpty() ? [-1] : $scope->sectionIds);
        }

        $assignments = $assignmentQuery->with(['section', 'subject', 'courseClass'])->get();

        $pendingGrading = [];
        foreach ($assignments as $a) {
            $submittedCount = AssignmentSubmission::where('assignment_id', $a->id)->count();
            $gradedCount    = AssignmentSubmission::where('assignment_id', $a->id)->whereNotNull('marks')->count();
            $ungraded       = $submittedCount - $gradedCount;
            if ($ungraded > 0) {
                $pendingGrading[] = [
                    'id'       => $a->id,
                    'title'    => $a->title,
                    'class'    => $a->courseClass?->name,
                    'section'  => $a->section?->name,
                    'subject'  => $a->subject?->name,
                    'due_date' => $a->due_date,
                    'ungraded' => $ungraded,
                    'total'    => $submittedCount,
                ];
            }
        }

        // Sort by ungraded desc
        usort($pendingGrading, fn($a, $b) => $b['ungraded'] - $a['ungraded']);

        // ── Syllabus Completion ───────────────────────────────────────────
        $topicQuery = SyllabusTopic::where('school_id', $schoolId);
        if ($scope->restricted && $scope->classIds->isNotEmpty()) {
            $topicQuery->whereIn('class_id', $scope->classIds);
        }
        $topics     = $topicQuery->pluck('id');
        $totalTopics    = $topics->count();
        $completedTopics = SyllabusStatus::whereIn('topic_id', $topics)
            ->where('status', 'completed')
            ->count();
        $syllabusCompletionPct = $totalTopics > 0
            ? round($completedTopics / $totalTopics * 100)
            : null;

        // ── Today's Online Classes ────────────────────────────────────────
        $classesQuery = OnlineClass::where('school_id', $schoolId)
            ->whereDate('start_time', today())
            ->with(['subject', 'section', 'courseClass']);
        if ($scope->restricted) {
            $classesQuery->whereIn('section_id', $scope->sectionIds->isEmpty() ? [-1] : $scope->sectionIds);
        }
        $todayClasses = $classesQuery->orderBy('start_time')->get();

        // ── Recent Diary Entries (last 7 days) ────────────────────────────
        $diaryQuery = StudentDiary::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('date', '>=', now()->subDays(7)->toDateString())
            ->with(['subject', 'section', 'courseClass'])
            ->withCount(['reads', 'completions']);
        if ($scope->restricted) {
            $diaryQuery->whereIn('section_id', $scope->sectionIds->isEmpty() ? [-1] : $scope->sectionIds);
        }
        $recentDiaries = $diaryQuery->latest('date')->limit(10)->get();

        // ── Upcoming Assignment Due Dates (next 7 days) ───────────────────
        $upcomingDue = $assignmentQuery->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->with(['subject', 'section', 'courseClass'])
            ->orderBy('due_date')
            ->get();

        return Inertia::render('School/Academic/Dashboard', [
            'pendingGrading'      => array_slice($pendingGrading, 0, 10),
            'syllabusCompletionPct' => $syllabusCompletionPct,
            'syllabusStats'       => ['total' => $totalTopics, 'completed' => $completedTopics],
            'todayClasses'        => $todayClasses,
            'recentDiaries'       => $recentDiaries,
            'upcomingDue'         => $upcomingDue,
        ]);
    }

    /**
     * P5: Unified Academic Calendar — returns events for a month combining
     * assignments, online classes, syllabus planned dates, diary entries.
     */
    public function calendar(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $scope          = app(TeacherScopeService::class)->for(auth()->user());

        $year  = (int) ($request->get('year',  now()->year));
        $month = (int) ($request->get('month', now()->month));
        $start = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        $events = [];

        // Assignments due this month
        $aQuery = Assignment::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereBetween('due_date', [$start, $end])
            ->with(['subject', 'courseClass', 'section']);
        if ($scope->restricted) {
            $aQuery->whereIn('section_id', $scope->sectionIds->isEmpty() ? [-1] : $scope->sectionIds);
        }
        if ($request->filled('class_id'))   $aQuery->where('class_id', $request->class_id);
        if ($request->filled('section_id')) $aQuery->where('section_id', $request->section_id);

        foreach ($aQuery->get() as $a) {
            $events[] = [
                'type'    => 'assignment',
                'date'    => $a->due_date instanceof \Carbon\Carbon ? $a->due_date->toDateString() : substr($a->due_date, 0, 10),
                'title'   => $a->title,
                'label'   => $a->subject?->name . ' · ' . $a->courseClass?->name . ($a->section ? '/'.$a->section->name : ''),
                'color'   => 'red',
                'link'    => route('school.academic.assignments.show', $a->id),
            ];
        }

        // Online classes this month
        $ocQuery = OnlineClass::where('school_id', $schoolId)
            ->whereBetween('start_time', [$start, $end])
            ->with(['subject', 'courseClass', 'section']);
        if ($scope->restricted) {
            $ocQuery->whereIn('section_id', $scope->sectionIds->isEmpty() ? [-1] : $scope->sectionIds);
        }
        if ($request->filled('class_id'))   $ocQuery->where('class_id', $request->class_id);
        if ($request->filled('section_id')) $ocQuery->where('section_id', $request->section_id);

        foreach ($ocQuery->get() as $oc) {
            $events[] = [
                'type'  => 'online_class',
                'date'  => \Carbon\Carbon::parse($oc->start_time)->toDateString(),
                'title' => $oc->subject?->name . ' — ' . ($oc->platform ?? 'Online Class'),
                'label' => \Carbon\Carbon::parse($oc->start_time)->format('h:i A') . ' · ' . $oc->courseClass?->name . ($oc->section ? '/'.$oc->section->name : ''),
                'color' => 'blue',
                'link'  => route('school.academic.resources.index'),
            ];
        }

        // Syllabus planned dates this month
        $spQuery = SyllabusStatus::whereNotNull('planned_date')
            ->whereBetween('planned_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('topic', fn($q) => $q->where('school_id', $schoolId))
            ->with(['topic.subject', 'topic.courseClass']);
        if ($request->filled('class_id')) {
            $spQuery->whereHas('topic', fn($q) => $q->where('class_id', $request->class_id));
        }

        foreach ($spQuery->get() as $sp) {
            $events[] = [
                'type'  => 'syllabus',
                'date'  => $sp->planned_date,
                'title' => $sp->topic?->topic_name,
                'label' => $sp->topic?->chapter_name . ' · ' . $sp->topic?->subject?->name,
                'color' => 'green',
                'link'  => route('school.academic.syllabus.index'),
            ];
        }

        // Diary entries this month
        $dQuery = StudentDiary::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with(['subject', 'courseClass', 'section']);
        if ($scope->restricted) {
            $dQuery->whereIn('section_id', $scope->sectionIds->isEmpty() ? [-1] : $scope->sectionIds);
        }
        if ($request->filled('class_id'))   $dQuery->where('class_id',   $request->class_id);
        if ($request->filled('section_id')) $dQuery->where('section_id', $request->section_id);

        foreach ($dQuery->get() as $d) {
            $events[] = [
                'type'  => 'diary',
                'date'  => $d->date instanceof \Carbon\Carbon ? $d->date->toDateString() : $d->date,
                'title' => 'Diary — ' . $d->subject?->name,
                'label' => $d->courseClass?->name . ($d->section ? '/'.$d->section->name : ''),
                'color' => 'purple',
                'link'  => route('school.academic.diary.index'),
            ];
        }

        return Inertia::render('School/Academic/Calendar', [
            'events'  => $events,
            'year'    => $year,
            'month'   => $month,
            'classes' => CourseClass::where('school_id', $schoolId)->with('sections')->orderBy('numeric_value')->get(['id', 'name']),
            'filters' => $request->only(['class_id', 'section_id']),
        ]);
    }

    /**
     * P5: Subject-wise Academic Health Score for admin/management.
     */
    public function healthScore(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $classes = CourseClass::where('school_id', $schoolId)
            ->with(['subjects', 'sections'])
            ->orderBy('numeric_value')
            ->get();

        $scores = [];

        foreach ($classes as $class) {
            foreach ($class->subjects as $subject) {
                // Syllabus completion
                $topicIds = SyllabusTopic::where('school_id', $schoolId)
                    ->where('class_id', $class->id)
                    ->where('subject_id', $subject->id)
                    ->pluck('id');

                $totalTopics     = $topicIds->count();
                $completedTopics = $totalTopics > 0
                    ? SyllabusStatus::whereIn('topic_id', $topicIds)->where('status', 'completed')->count()
                    : 0;
                $syllabusPct = $totalTopics > 0 ? round($completedTopics / $totalTopics * 100) : null;

                // Assignment stats (this academic year)
                $aIds = Assignment::where('school_id', $schoolId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('class_id', $class->id)
                    ->where('subject_id', $subject->id)
                    ->pluck('id');

                $totalAssignments = $aIds->count();
                $gradedAssignments = $totalAssignments > 0
                    ? Assignment::whereIn('id', $aIds)
                        ->whereHas('submissions', fn($q) => $q->whereNotNull('marks'))
                        ->count()
                    : 0;

                // Average marks
                $avgMarks = null;
                if ($totalAssignments > 0) {
                    $avgRaw = AssignmentSubmission::whereIn('assignment_id', $aIds)
                        ->whereNotNull('marks')
                        ->avg('marks');
                    $avgMarks = $avgRaw ? round($avgRaw, 1) : null;
                }

                // Diary entries this week
                $diaryCount = StudentDiary::where('school_id', $schoolId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('class_id', $class->id)
                    ->where('subject_id', $subject->id)
                    ->where('date', '>=', now()->subDays(7)->toDateString())
                    ->count();

                // Health score = weighted average of syllabus % + grading %
                $gradingPct = $totalAssignments > 0 ? round($gradedAssignments / $totalAssignments * 100) : null;
                $healthScore = null;
                if ($syllabusPct !== null && $gradingPct !== null) {
                    $healthScore = round(($syllabusPct * 0.6) + ($gradingPct * 0.4));
                } elseif ($syllabusPct !== null) {
                    $healthScore = $syllabusPct;
                } elseif ($gradingPct !== null) {
                    $healthScore = $gradingPct;
                }

                if ($totalTopics === 0 && $totalAssignments === 0) continue; // skip unused combos

                $scores[] = [
                    'class_id'    => $class->id,
                    'class_name'  => $class->name,
                    'subject_id'  => $subject->id,
                    'subject_name'=> $subject->name,
                    'syllabus_pct'=> $syllabusPct,
                    'grading_pct' => $gradingPct,
                    'avg_marks'   => $avgMarks,
                    'diary_week'  => $diaryCount,
                    'health_score'=> $healthScore,
                    'total_topics'=> $totalTopics,
                    'total_assignments' => $totalAssignments,
                ];
            }
        }

        // Sort by health score asc (weakest first)
        usort($scores, fn($a, $b) => ($a['health_score'] ?? 999) <=> ($b['health_score'] ?? 999));

        return Inertia::render('School/Academic/HealthScore', [
            'scores'  => $scores,
            'classes' => $classes->map(fn($c) => ['id' => $c->id, 'name' => $c->name]),
            'filters' => $request->only(['class_id']),
        ]);
    }
}
