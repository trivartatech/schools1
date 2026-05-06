<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExamSchedule;
use App\Models\ExamTerm;
use App\Models\CourseClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\ExamMark;
use Inertia\Inertia;

class ReportCardController extends Controller
{
    private $globalSchGrades = null;
    private $globalCoGrades = null;

    public function index(Request $request)
    {
        if (! $request->user()->can('manage_exam_terms')) abort(403);

        $schoolId = app('current_school_id');
        $yearId   = app('current_academic_year_id');

        $schedules = ExamSchedule::with(['examType', 'courseClass', 'sections'])
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $yearId)
            ->latest()
            ->get();

        $examTerms = ExamTerm::with('examTypes')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $yearId)
            ->get();

        $classes = CourseClass::where('school_id', $schoolId)
            ->with(['sections' => fn($q) => $q->forCurrentYear()->select('id', 'course_class_id', 'name')])
            ->orderBy('numeric_value')->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('School/Examinations/ReportCards/Index', [
            'schedules' => $schedules,
            'examTerms' => $examTerms,
            'classes'   => $classes,
        ]);
    }

    // ─── Shared calculation helper ───────────────────────────────────────────────
    private function calcSubjectScore($ss, $studentMarks, ?float $weightage = null): array
    {
        $ssMarks   = $studentMarks->where('exam_schedule_subject_id', $ss->id);
        $subjectMax = 0;
        $subjectObtained = 0;
        $isAbsent  = false;

        if ($ss->examAssessment) {
            foreach ($ss->examAssessment->items as $item) {
                $m = $ss->markConfigs->firstWhere('exam_assessment_item_id', $item->id);
                $subjectMax += $m ? ($m->max_marks ?? 0) : ($item->max_marks ?? 0);
            }
        }

        foreach ($ssMarks as $markLine) {
            if ($markLine->is_absent) {
                $isAbsent = true;
            } else {
                $subjectObtained += (float) $markLine->marks_obtained;
            }
        }

        $percentage = $subjectMax > 0 ? ($subjectObtained / $subjectMax) * 100 : 0;

        // Weighted contribution: (obtained / max) × weightage
        $weightedContribution = null;
        if ($weightage !== null && !$isAbsent && $subjectMax > 0) {
            $weightedContribution = round(($subjectObtained / $subjectMax) * $weightage, 2);
        }

        $grade = null; $gradePoint = null;
        if ($this->globalSchGrades === null) {
            $this->globalSchGrades = \App\Models\GradingSystem::with('grades')
                ->where('school_id', app('current_school_id'))
                ->where('type', 'scholastic')
                ->first()
                ?->grades ?? collect();
        }

        $grades = $ss->gradingSystem ? $ss->gradingSystem->grades : $this->globalSchGrades;

        if ($grades->isNotEmpty()) {
            $matched = $grades->sortByDesc('min_percentage')->first(fn ($g) => 
                (float)$percentage >= (float)$g->min_percentage
            );
            if ($matched) { 
                $grade = $matched->name; 
                $gradePoint = $matched->grade_point; 
            }
        }

        return [
            'subject_name'           => $ss->subject->name ?? '',
            'obtained'               => $isAbsent ? 'ABS' : $subjectObtained,
            'max'                    => $subjectMax,
            'percentage'             => round($percentage, 2),
            'grade'                  => $grade,
            'grade_point'            => $gradePoint,
            'weighted_contribution'  => $weightedContribution,
        ];
    }

    // ─── Single-exam student calculation ─────────────────────────────────────────
    private function attachSingleExamReports($students, $schedule): void
    {
        $studentIds = $students->pluck('id');
        $scheduleSubjectIds = $schedule->scheduleSubjects->pluck('id');

        $examMarks = ExamMark::whereIn('student_id', $studentIds)
            ->whereIn('exam_schedule_subject_id', $scheduleSubjectIds)
            ->get()->groupBy('student_id');

        foreach ($students as $student) {
            $studentMarks = $examMarks->get($student->id, collect());
            $subjectScores = [];
            $totalObtained = 0; $totalMax = 0;

            foreach ($schedule->scheduleSubjects->filter(fn ($ss) => !$ss->is_co_scholastic) as $ss) {
                $score = $this->calcSubjectScore($ss, $studentMarks);
                $subjectScores[] = $score;
                $totalMax += $score['max'];
                if ($score['obtained'] !== 'ABS') $totalObtained += $score['obtained'];
            }

            $overall = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 2) : 0;
            $student->report_calculated = [
                'mode'               => 'single',
                'subjects'           => $subjectScores,
                'total_obtained'     => $totalObtained,
                'total_max'          => $totalMax,
                'overall_percentage' => $overall,
                'co_scholastic'      => $this->calcCoScholasticData(
                    $student, $studentMarks, $schedule, collect([$schedule]), [['code' => ($schedule->examType->code ?? 'SINGLE'), 'name' => $schedule->examType->name ?? 'Exam']]
                ),
            ];
        }
    }

    // ─── Co-scholastic grade computation ─────────────────────────────────────────
    // Returns array of [ subject_name, exams => [ [code, name, grade] ] ]
    private function calcCoScholasticData($student, $studentMarks, $baseSchedule, $allSchedules, array $examCols): array
    {
        $coSubjects = $baseSchedule->scheduleSubjects->where('is_co_scholastic', true);
        if ($coSubjects->isEmpty()) return [];

        // Pre-build a map of subject_id -> [ exam_type_code -> schedule_subject_id ]
        // to quickly find the correct mark for each column
        $subjectIdMap = [];
        foreach ($coSubjects as $ss) {
            $sid = $ss->subject_id;
            $subjectIdMap[$sid] = [];
            foreach ($allSchedules as $s) {
                $code = $s->examType->code ?? 'SINGLE';
                $targetSs = $s->scheduleSubjects->where('subject_id', $sid)->first();
                if ($targetSs) {
                    $subjectIdMap[$sid][$code] = $targetSs->id;
                }
            }
        }

        if ($this->globalCoGrades === null) {
            $this->globalCoGrades = \App\Models\GradingSystem::with('grades')
                ->where('school_id', app('current_school_id'))
                ->where('type', 'co_scholastic')
                ->first()
                ?->grades ?? collect();
        }
        
        if ($this->globalSchGrades === null) {
            $this->globalSchGrades = \App\Models\GradingSystem::with('grades')
                ->where('school_id', app('current_school_id'))
                ->where('type', 'scholastic')
                ->first()
                ?->grades ?? collect();
        }

        $result = [];
        foreach ($coSubjects as $ss) {
            $sid   = $ss->subject_id;
            $sName = $ss->subject->name ?? '';
            $examEntries = [];

            foreach ($examCols as $col) {
                $code = $col['code'];
                // Map col to schedule
                $targetSched = $allSchedules->first(fn ($s) => ($s->examType->code ?? 'SINGLE') == $code);
                
                // Fallback for single mode where code might be 'SINGLE' but s->examType->code is not null
                if (!$targetSched && $code === 'SINGLE') {
                    $targetSched = $baseSchedule ?? $allSchedules->first();
                }

                $targetSsId = null;
                if ($targetSched) {
                    $targetSsId = $targetSched->scheduleSubjects
                        ->firstWhere('subject_id', $ss->subject_id)
                        ?->id;
                }
                $grade = '—';

                if ($targetSsId) {
                    $mark = $studentMarks->first(fn ($m) => $m->exam_schedule_subject_id == $targetSsId);
                    if ($mark && $mark->marks_obtained !== null) {
                        if ($mark->is_absent) {
                            $grade = 'ABS';
                        } elseif (is_numeric($mark->marks_obtained)) {
                            // Find the max marks for this assessment item to calculate percentage
                            $targetSs = $allSchedules->flatMap->scheduleSubjects->firstWhere('id', $targetSsId);
                            $maxMarks = 100; // default
                            if ($targetSs && $targetSs->markConfigs->isNotEmpty()) {
                                $maxMarks = (float)($targetSs->markConfigs->first()->max_marks ?? 100);
                            }
                            
                            $pct = $maxMarks > 0 ? ((float)$mark->marks_obtained / $maxMarks) * 100 : 0;
                            
                            // Use subject-specific grading system if available, else fallback
                            $grades = ($targetSs && $targetSs->gradingSystem) ? $targetSs->gradingSystem->grades : $this->globalCoGrades;
                            $matched = $grades->sortByDesc('min_percentage')->first(fn($g) => (float)$pct >= (float)$g->min_percentage);
                            $grade = $matched?->name ?? '—';
                        } else {
                            // Already a grade string
                            $grade = $mark->marks_obtained;
                        }
                    }
                }

                $examEntries[] = ['code' => $code, 'name' => $col['name'], 'grade' => $grade];
            }

            $result[] = ['subject_name' => $sName, 'exams' => $examEntries];
        }

        return $result;
    }

    // ─── Weighted cumulative calculation across ALL exam types ───────────────────
    // $baseSchedule: the schedule the user selected — its subjects define the master list
    private function attachWeightedReports($students, $allSchedules, $baseSchedule): void
    {
        $studentIds = $students->pluck('id');

        // Gather all schedule-subject IDs
        $allSsIds = $allSchedules->flatMap(fn ($s) => $s->scheduleSubjects->pluck('id'));

        $examMarks = ExamMark::whereIn('student_id', $studentIds)
            ->whereIn('exam_schedule_subject_id', $allSsIds)
            ->get()->groupBy('student_id');

        // ★ Only use subjects from the SELECTED (base) schedule as the master list.
        // Other exam types contribute marks for these subjects but cannot add new rows.
        $subjectNames = $baseSchedule->scheduleSubjects
            ->filter(fn ($ss) => !$ss->is_co_scholastic && $ss->examAssessment)
            ->map(fn ($ss) => $ss->subject->name ?? '')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Build exam type metadata list (include term grouping)
        $examTypes = $allSchedules->map(fn ($s) => [
            'name'      => $s->examType->name ?? '',
            'code'      => $s->examType->code ?? '',
            'weightage' => (float) ($s->weightage ?? 0),
            'term_id'   => $s->examType->exam_term_id ?? null,
            'term_name' => $s->examType->examTerm->name ?? 'Term',
        ]);

        foreach ($students as $student) {
            $studentMarks = $examMarks->get($student->id, collect());

            // Per-subject cumulative weighted data
            // structure: $map[subject_name] = [contributions array, grade info from last exam]
            $map = [];
            foreach ($subjectNames as $sn) {
                $map[$sn] = ['contributions' => [], 'grade' => null, 'grade_point' => null, 'percentage' => 0];
            }

            foreach ($allSchedules as $schedule) {
                $weightage = (float) ($schedule->weightage ?? 0);
                foreach ($schedule->scheduleSubjects as $ss) {
                    $name = $ss->subject->name ?? '';
                    $score = $this->calcSubjectScore($ss, $studentMarks, $weightage);

                    if (!isset($map[$name])) continue;

                    $map[$name]['contributions'][] = [
                        'exam'       => $schedule->examType->name ?? '',
                        'code'       => $schedule->examType->code ?? '',
                        'weightage'  => $weightage,
                        'term_id'    => $schedule->examType->exam_term_id ?? null,
                        'term_name'  => $schedule->examType->examTerm->name ?? 'Term',
                        'obtained'   => $score['obtained'],
                        'max'        => $score['max'],
                        'percentage' => $score['percentage'],
                        'weighted'   => $score['weighted_contribution'],
                        'grade'      => $score['grade'],      // grade for THIS exam
                    ];

                    // Use the grade from the highest-weightage exam
                    if ($score['grade'] && $weightage >= ($map[$name]['top_weightage'] ?? 0)) {
                        $map[$name]['grade'] = $score['grade'];
                        $map[$name]['grade_point'] = $score['grade_point'];
                        $map[$name]['percentage'] = $score['percentage'];
                        $map[$name]['top_weightage'] = $weightage;
                    }
                }
            }

            // Build final subject scores with weighted totals
            $subjectScores = [];
            $totalWeightedObtained = 0;
            $totalWeightage = $examTypes->sum('weightage');

            foreach ($subjectNames as $name) {
                $data = $map[$name];
                $weightedTotal = collect($data['contributions'])->sum('weighted');
                $subjectScores[] = [
                    'subject_name'          => $name,
                    'contributions'         => $data['contributions'],
                    'weighted_total'        => round($weightedTotal, 2),
                    'percentage'            => $data['percentage'],
                    'grade'                 => $data['grade'],
                    'grade_point'           => $data['grade_point'],
                ];
                $totalWeightedObtained += $weightedTotal;
            }

            $student->report_calculated = [
                'mode'              => 'weighted',
                'exam_types'        => $examTypes->values(),
                'subjects'          => $subjectScores,
                'total_weighted'    => round($totalWeightedObtained, 2),
                'total_weightage'   => $totalWeightage,
                'overall_percentage' => round($totalWeightedObtained, 2), // weighted % IS the final %
                'co_scholastic'     => $this->calcCoScholasticData(
                    $student, $studentMarks, $baseSchedule, $allSchedules,
                    $examTypes->map(fn ($et) => ['code' => $et['code'], 'name' => $et['name']])->values()->all()
                ),
            ];
        }
    }

    // ─── Rank assignment ─────────────────────────────────────────────────────────
    private function assignRanks($students): void
    {
        // Sort by overall_percentage desc, then name asc for tie-breaking
        $sorted = $students->sortByDesc(fn ($s) => $s->report_calculated['overall_percentage'] ?? 0)
                           ->values();

        $rank = 1;
        $prevPct = null;
        $prevRank = 1;
        foreach ($sorted as $i => $student) {
            $pct = $student->report_calculated['overall_percentage'] ?? 0;
            if ($prevPct !== null && $pct < $prevPct) {
                $prevRank = $rank;
            }
            $calc = $student->report_calculated;
            $calc['rank']        = $prevRank;
            $calc['total_count'] = $students->count();
            $student->report_calculated = $calc;
            $prevPct = $pct;
            $rank++;
        }
    }

    // ─── Resolve report context from request ────────────────────────────────────
    // Returns: [reportType, applyWeightage, baseSchedule, allSchedules, courseClassId, sectionId]
    private function resolveReportContext(Request $request): array
    {
        $reportType     = $request->input('report_type', 'exam');
        $applyWeightage = (bool) $request->input('apply_weightage', false);
        $sectionId      = $request->input('section_id');

        $schoolId = app('current_school_id');
        $yearId   = app('current_academic_year_id');

        $scheduleWith = [
            'examType.examTerm', 'courseClass',
            'scheduleSubjects' => fn($q) => $q->where('is_enabled', true)
                ->with(['subject', 'examAssessment.items', 'markConfigs', 'gradingSystem.grades'])
        ];

        switch ($reportType) {
            case 'term':
                $request->validate([
                    'exam_term_id'    => 'required|exists:exam_terms,id',
                    'course_class_id' => 'required|exists:course_classes,id',
                    'section_id'      => 'required|exists:sections,id',
                ]);
                $allSchedules = ExamSchedule::with($scheduleWith)
                    ->where('school_id', $schoolId)
                    ->where('academic_year_id', $yearId)
                    ->where('course_class_id', $request->course_class_id)
                    ->whereHas('examType', fn($q) => $q->where('exam_term_id', $request->exam_term_id))
                    ->get();
                if ($allSchedules->isEmpty()) {
                    abort(404, 'No exam schedules found in this term for this class.');
                }
                $baseSchedule  = $allSchedules->sortByDesc('weightage')->first();
                $courseClassId = $request->course_class_id;
                break;

            case 'cumulative':
                $request->validate([
                    'exam_schedule_id' => 'required|exists:exam_schedules,id',
                    'section_id'       => 'required|exists:sections,id',
                ]);
                $baseSchedule = ExamSchedule::with($scheduleWith)
                    ->where('school_id', $schoolId)
                    ->findOrFail($request->exam_schedule_id);
                $allSchedules = ExamSchedule::with($scheduleWith)
                    ->where('school_id', $schoolId)
                    ->where('academic_year_id', $yearId)
                    ->where('course_class_id', $baseSchedule->course_class_id)
                    ->get();
                $courseClassId = $baseSchedule->course_class_id;
                break;

            case 'exam':
            default:
                $request->validate([
                    'exam_schedule_id' => 'required|exists:exam_schedules,id',
                    'section_id'       => 'required|exists:sections,id',
                ]);
                $reportType   = 'exam';
                $baseSchedule = ExamSchedule::with($scheduleWith)
                    ->where('school_id', $schoolId)
                    ->findOrFail($request->exam_schedule_id);
                $allSchedules = collect([$baseSchedule]);
                $courseClassId = $baseSchedule->course_class_id;
                break;
        }

        return [$reportType, $applyWeightage, $baseSchedule, $allSchedules, $courseClassId, $sectionId];
    }

    // ─── Run the right calculation pipeline ──────────────────────────────────────
    private function runCalculation($students, string $reportType, bool $applyWeightage, $baseSchedule, $allSchedules): void
    {
        // Exam-wise + raw → single-exam path (does not surface contributions[])
        // Everything else → weighted aggregator (always surfaces contributions[]; the toggle only controls how the frontend renders them)
        if ($reportType === 'exam' && ! $applyWeightage) {
            $this->attachSingleExamReports($students, $baseSchedule);
        } else {
            $this->attachWeightedReports($students, $allSchedules, $baseSchedule);
        }
    }

    // ─── Sort students by roll-no within a section + attach roll_no field ───────
    private function attachRollAndSort($students, $sectionId)
    {
        return $students
            ->sortBy(function ($s) use ($sectionId) {
                $hist = $s->academicHistories->firstWhere('section_id', $sectionId);
                return [is_null($hist?->roll_no) ? PHP_INT_MAX : (int) $hist->roll_no, $s->first_name];
            })
            ->values()
            ->each(function ($s) use ($sectionId) {
                $hist = $s->academicHistories->firstWhere('section_id', $sectionId);
                $s->roll_no = $hist?->roll_no;
            });
    }

    // ─── Generate (AJAX) ─────────────────────────────────────────────────────────
    public function generate(Request $request)
    {
        if (! $request->user()->can('manage_exam_terms')) abort(403);

        [$reportType, $applyWeightage, $baseSchedule, $allSchedules, $courseClassId, $sectionId]
            = $this->resolveReportContext($request);

        $schoolId = app('current_school_id');
        $yearId   = app('current_academic_year_id');

        $students = Student::with(['studentParent', 'academicHistories'])
            ->where('school_id', $schoolId)
            ->whereHas('academicHistories', fn($q) =>
                $q->where('academic_year_id', $yearId)
                  ->where('class_id', $courseClassId)
                  ->where('section_id', $sectionId)
            )
            ->where('status', 'active')
            ->get();

        $students = $this->attachRollAndSort($students, $sectionId);

        $this->runCalculation($students, $reportType, $applyWeightage, $baseSchedule, $allSchedules);
        $this->assignRanks($students);

        return response()->json([
            'report_type'     => $reportType,
            'apply_weightage' => $applyWeightage,
            'schedule'        => $baseSchedule,
            'students'        => $students,
        ]);
    }

    // ─── Print ───────────────────────────────────────────────────────────────────
    public function print(Request $request)
    {
        if (! $request->user()->can('manage_exam_terms')) abort(403);

        $request->validate(['student_ids' => 'required|string']);

        [$reportType, $applyWeightage, $baseSchedule, $allSchedules, , $sectionId]
            = $this->resolveReportContext($request);

        $studentIds = array_filter(explode(',', $request->student_ids));

        $students = Student::with(['studentParent', 'academicHistories'])
            ->where('school_id', app('current_school_id'))
            ->whereIn('id', $studentIds)
            ->where('status', 'active')
            ->get();

        $students = $this->attachRollAndSort($students, $sectionId);

        $this->runCalculation($students, $reportType, $applyWeightage, $baseSchedule, $allSchedules);
        $this->assignRanks($students);

        $gradeScale = \App\Models\GradingSystem::with('grades')
            ->where('school_id', app('current_school_id'))
            ->where('type', 'scholastic')
            ->first()
            ?->grades
            ->sortByDesc('min_percentage')
            ->values();

        // Pull the term name for the print pill when in term mode
        $termName = null;
        if ($reportType === 'term') {
            $termName = $baseSchedule->examType?->examTerm?->name ?? null;
        }

        return Inertia::render('School/Examinations/ReportCards/Print', [
            'scheduleData'   => $baseSchedule,
            'students'       => $students,
            'sectionData'    => Section::find($sectionId),
            'schoolInfo'     => app('current_school'),
            'academicYear'   => app('current_academic_year')?->name ?? '',
            'useWeightage'   => $applyWeightage,
            'reportType'     => $reportType,
            'termName'       => $termName,
            'gradeScale'     => $gradeScale,
        ]);
    }
}
