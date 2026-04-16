<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\ExamMark;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExamResultController extends Controller
{
    public function index()
    {
        $schedules = ExamSchedule::with(['examType', 'courseClass', 'sections'])
            ->where('school_id', app('current_school_id'))
            ->where('academic_year_id', app('current_academic_year_id'))
            ->where('status', 'published')
            ->latest()
            ->get();

        return Inertia::render('School/Examinations/Results/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function data(Request $request)
    {
        $request->validate([
            'exam_schedule_id' => 'required|exists:exam_schedules,id',
            'section_id'       => 'required|exists:sections,id',
        ]);

        return response()->json(
            $this->buildResultData(
                (int) $request->exam_schedule_id,
                (int) $request->section_id
            )
        );
    }

    public function print(Request $request)
    {
        $request->validate([
            'exam_schedule_id' => 'required|exists:exam_schedules,id',
            'section_id'       => 'required|exists:sections,id',
        ]);

        $result = $this->buildResultData(
            (int) $request->exam_schedule_id,
            (int) $request->section_id
        );

        return Inertia::render('School/Examinations/Results/Print', array_merge($result, [
            'schoolInfo'   => app('current_school'),
            'academicYear' => app('current_academic_year')->name,
        ]));
    }

    private function buildResultData(int $scheduleId, int $sectionId): array
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $schedule = ExamSchedule::with([
            'examType', 'courseClass',
            'scheduleSubjects' => fn($q) => $q->where('is_enabled', true)
                ->where('is_co_scholastic', false)
                ->with(['subject', 'examAssessment.items', 'markConfigs']),
        ])
        ->where('school_id', $schoolId)
        ->findOrFail($scheduleId);

        $section = Section::where('school_id', $schoolId)->findOrFail($sectionId);

        // Students sorted by roll_no from the current-year history for this section.
        $students = Student::with(['academicHistories' => fn($q) =>
                $q->where('academic_year_id', $academicYearId)
                  ->where('section_id', $sectionId)
            ])
            ->where('school_id', $schoolId)
            ->whereHas('academicHistories', fn($q) =>
                $q->where('academic_year_id', $academicYearId)
                  ->where('class_id', $schedule->course_class_id)
                  ->where('section_id', $sectionId)
            )
            ->where('status', 'active')
            ->get()
            ->sortBy(function ($s) {
                $hist = $s->academicHistories->first();
                return [is_null($hist?->roll_no) ? PHP_INT_MAX : (int)$hist->roll_no, $s->first_name];
            })
            ->values();

        $students->each(function ($s) use ($sectionId) {
            $s->roll_no = $s->academicHistories->first()?->roll_no;
        });

        $subjects = $schedule->scheduleSubjects
            ->filter(fn($ss) => $ss->examAssessment)
            ->values();

        // Max marks and passing marks per schedule subject
        $subjectMaxMap  = [];
        $subjectPassMap = [];
        foreach ($subjects as $ss) {
            $max  = 0;
            $pass = 0;
            foreach ($ss->examAssessment->items as $item) {
                $cfg  = $ss->markConfigs->firstWhere('exam_assessment_item_id', $item->id);
                $max  += $cfg ? (float)$cfg->max_marks     : (float)($item->max_marks  ?? 0);
                $pass += $cfg ? (float)$cfg->passing_marks : 0;
            }
            $subjectMaxMap[$ss->id]  = $max;
            $subjectPassMap[$ss->id] = $pass;
        }

        $scheduleSubjectIds = $subjects->pluck('id');
        $studentIds         = $students->pluck('id');

        $marksMap = ExamMark::whereIn('student_id', $studentIds)
            ->whereIn('exam_schedule_subject_id', $scheduleSubjectIds)
            ->get()
            ->groupBy('student_id');

        $rows = [];
        foreach ($students as $student) {
            $studentMarks  = $marksMap->get($student->id, collect());
            $subjectResults = [];
            $totalObtained  = 0;
            $totalMax       = 0;
            $hasAbsent      = false;

            foreach ($subjects as $ss) {
                $ssMarks  = $studentMarks->where('exam_schedule_subject_id', $ss->id);
                $max      = $subjectMaxMap[$ss->id] ?? 0;
                $obtained = 0;
                $absent   = false;

                foreach ($ssMarks as $m) {
                    if ($m->is_absent) { $absent = true; break; }
                    $obtained += (float)$m->marks_obtained;
                }

                $pct        = (!$absent && $max > 0) ? round(($obtained / $max) * 100, 1) : null;
                $passMarks  = $subjectPassMap[$ss->id] ?? 0;
                $subjectFail = !$absent && $pct !== null && (
                    $passMarks > 0 ? $obtained < $passMarks : $pct < 33
                );

                $subjectResults[] = [
                    'subject_id'   => $ss->subject_id,
                    'subject_name' => $ss->subject->name ?? '',
                    'obtained'     => $absent ? null : $obtained,
                    'max'          => $max,
                    'absent'       => $absent,
                    'percentage'   => $pct,
                    'fail'         => $subjectFail,
                ];

                $totalMax += $max;
                if (!$absent) $totalObtained += $obtained;
                else $hasAbsent = true;
            }

            $overallPct = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 2) : 0;

            $rows[] = [
                'id'             => $student->id,
                'name'           => $student->first_name . ' ' . $student->last_name,
                'roll_no'        => $student->roll_no,
                'admission_no'   => $student->admission_no,
                'subjects'       => $subjectResults,
                'total_obtained' => $totalObtained,
                'total_max'      => $totalMax,
                'percentage'     => $overallPct,
                'has_absent'     => $hasAbsent,
                'rank'           => 0,
            ];
        }

        // Assign ranks (tied percentages get same rank)
        $sorted = collect($rows)->sortByDesc('percentage')->values()->all();
        $rank = 1; $prevPct = null; $prevRank = 1;
        $rankMap = [];
        foreach ($sorted as $i => $row) {
            if ($prevPct !== null && $row['percentage'] < $prevPct) $prevRank = $rank;
            $rankMap[$row['id']] = $prevRank;
            $prevPct = $row['percentage'];
            $rank++;
        }
        foreach ($rows as &$row) {
            $row['rank'] = $rankMap[$row['id']] ?? 0;
        }
        unset($row);

        // Summary statistics
        $percentages = collect($rows)->pluck('percentage');
        $passRows    = collect($rows)->where('percentage', '>=', 33);
        $topperRow   = collect($rows)->sortByDesc('percentage')->first();

        $stats = [
            'total'   => count($rows),
            'pass'    => $passRows->count(),
            'fail'    => count($rows) - $passRows->count(),
            'highest' => round($percentages->max() ?? 0, 2),
            'lowest'  => round($percentages->min() ?? 0, 2),
            'average' => round($percentages->avg() ?? 0, 2),
            'topper'  => $topperRow ? $topperRow['name'] : null,
        ];

        return [
            'schedule' => [
                'id'         => $schedule->id,
                'name'       => $schedule->examType->name ?? '',
                'class_name' => $schedule->courseClass->name ?? '',
            ],
            'section'  => ['id' => $section->id, 'name' => $section->name],
            'subjects' => $subjects->map(fn($ss) => [
                'id'   => $ss->subject_id,
                'name' => $ss->subject->name ?? '',
            ])->values(),
            'rows'     => array_values($rows),
            'stats'    => $stats,
        ];
    }
}
