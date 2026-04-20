<?php

namespace App\Services;

use App\Enums\RolloverState;
use App\Models\AcademicYear;
use App\Models\ExamTerm;
use App\Models\ExamType;
use App\Models\FeeConcession;
use App\Models\FeeStructure;
use App\Models\Grade;
use App\Models\GradingSystem;
use App\Models\RolloverRun;
use App\Models\School;
use Illuminate\Support\Facades\DB;

/**
 * Clones per-year structural configuration from the source year into the target year.
 *
 * School-wide tables (departments, course_classes, sections, subjects, class_subjects,
 * fee_heads, fee_groups) are NOT cloned — they already apply to every year. Only tables
 * keyed by academic_year_id get replicated.
 *
 * Every clone is recorded on the RolloverRun's item log so the run can be audited or
 * partially reversed.
 */
class AcademicRolloverService
{
    /** Supported module keys for the $config['modules'] array. */
    public const MODULE_FEE_STRUCTURES = 'fee_structures';
    public const MODULE_EXAM_TERMS     = 'exam_terms';
    public const MODULE_GRADING        = 'grading_systems';
    public const MODULE_CONCESSIONS    = 'fee_concessions';

    public const ALL_MODULES = [
        self::MODULE_FEE_STRUCTURES,
        self::MODULE_EXAM_TERMS,
        self::MODULE_GRADING,
        self::MODULE_CONCESSIONS,
    ];

    /**
     * Clone per-year config from source into target. Must be called inside a RolloverRun.
     * Idempotent per (run, item_type, source_id) — re-running the same run won't duplicate.
     *
     * @return array<string,array{created:int,skipped:int,failed:int}>
     */
    public function execute(RolloverRun $run, array $modules = self::ALL_MODULES): array
    {
        $school      = $run->school;
        $sourceYear  = $run->sourceYear;
        $targetYear  = $run->targetYear;

        $run->transitionTo(RolloverState::StructureRunning);

        $stats = [];
        $mapping = [
            'grading_systems' => [],
            'exam_terms'      => [],
            'exam_types'      => [],
            'fee_structures'  => [],
        ];

        try {
            DB::transaction(function () use ($run, $school, $sourceYear, $targetYear, $modules, &$stats, &$mapping) {
                if (in_array(self::MODULE_GRADING, $modules, true)) {
                    [$stats[self::MODULE_GRADING], $mapping['grading_systems']] =
                        $this->cloneGradingSystems($run, $school, $sourceYear, $targetYear);
                }

                if (in_array(self::MODULE_EXAM_TERMS, $modules, true)) {
                    [$termStats, $mapping['exam_terms']] =
                        $this->cloneExamTerms($run, $school, $sourceYear, $targetYear);
                    [$typeStats, $mapping['exam_types']] =
                        $this->cloneExamTypes($run, $school, $sourceYear, $targetYear, $mapping['exam_terms']);
                    $stats[self::MODULE_EXAM_TERMS] = $this->mergeCounts($termStats, $typeStats);
                }

                if (in_array(self::MODULE_FEE_STRUCTURES, $modules, true)) {
                    [$stats[self::MODULE_FEE_STRUCTURES], $mapping['fee_structures']] =
                        $this->cloneFeeStructures($run, $school, $sourceYear, $targetYear);
                }

                if (in_array(self::MODULE_CONCESSIONS, $modules, true)) {
                    $stats[self::MODULE_CONCESSIONS] =
                        $this->cloneFeeConcessions($run, $school, $sourceYear, $targetYear);
                }
            });

            foreach ($stats as $key => $counts) {
                $run->setStat("structure.{$key}", $counts);
            }
            $run->setStat('structure.mapping', $mapping);
            $run->transitionTo(RolloverState::StructureDone);

            return $stats;
        } catch (\Throwable $e) {
            $run->error = $e->getMessage();
            $run->transitionTo(RolloverState::Failed);
            throw $e;
        }
    }

    // ---------- Grading systems (and their grades) ----------

    private function cloneGradingSystems(RolloverRun $run, School $school, AcademicYear $sourceYear, AcademicYear $targetYear): array
    {
        $counts = ['created' => 0, 'skipped' => 0, 'failed' => 0];
        $map    = [];

        $systems = GradingSystem::with('grades')
            ->where('school_id', $school->id)
            ->where('academic_year_id', $sourceYear->id)
            ->get();

        foreach ($systems as $src) {
            $existing = GradingSystem::where('school_id', $school->id)
                ->where('academic_year_id', $targetYear->id)
                ->where('name', $src->name)
                ->first();

            if ($existing) {
                $map[$src->id] = $existing->id;
                $run->logItem('structure', 'grading_system', $src->id, $existing->id, 'skipped', 'already exists');
                $counts['skipped']++;
                continue;
            }

            $new = $src->replicate(['academic_year_id']);
            $new->academic_year_id = $targetYear->id;
            $new->save();

            foreach ($src->grades as $grade) {
                $newGrade = $grade->replicate(['grading_system_id']);
                $newGrade->grading_system_id = $new->id;
                $newGrade->save();
            }

            $map[$src->id] = $new->id;
            $run->logItem('structure', 'grading_system', $src->id, $new->id, 'success', null, [
                'grades_cloned' => $src->grades->count(),
            ]);
            $counts['created']++;
        }

        return [$counts, $map];
    }

    // ---------- Exam terms ----------

    private function cloneExamTerms(RolloverRun $run, School $school, AcademicYear $sourceYear, AcademicYear $targetYear): array
    {
        $counts = ['created' => 0, 'skipped' => 0, 'failed' => 0];
        $map    = [];

        $terms = ExamTerm::where('school_id', $school->id)
            ->where('academic_year_id', $sourceYear->id)
            ->get();

        foreach ($terms as $src) {
            $existing = ExamTerm::where('school_id', $school->id)
                ->where('academic_year_id', $targetYear->id)
                ->where('name', $src->name)
                ->first();

            if ($existing) {
                $map[$src->id] = $existing->id;
                $run->logItem('structure', 'exam_term', $src->id, $existing->id, 'skipped', 'already exists');
                $counts['skipped']++;
                continue;
            }

            $new = $src->replicate(['academic_year_id']);
            $new->academic_year_id = $targetYear->id;
            $new->save();

            $map[$src->id] = $new->id;
            $run->logItem('structure', 'exam_term', $src->id, $new->id, 'success');
            $counts['created']++;
        }

        return [$counts, $map];
    }

    // ---------- Exam types (depend on term mapping) ----------

    private function cloneExamTypes(RolloverRun $run, School $school, AcademicYear $sourceYear, AcademicYear $targetYear, array $termMap): array
    {
        $counts = ['created' => 0, 'skipped' => 0, 'failed' => 0];
        $map    = [];

        $types = ExamType::where('school_id', $school->id)
            ->where('academic_year_id', $sourceYear->id)
            ->get();

        foreach ($types as $src) {
            if (!isset($termMap[$src->exam_term_id])) {
                $run->logItem('structure', 'exam_type', $src->id, null, 'skipped', 'parent term not mapped');
                $counts['skipped']++;
                continue;
            }

            $existing = ExamType::where('school_id', $school->id)
                ->where('academic_year_id', $targetYear->id)
                ->where('exam_term_id', $termMap[$src->exam_term_id])
                ->where('name', $src->name)
                ->first();

            if ($existing) {
                $map[$src->id] = $existing->id;
                $run->logItem('structure', 'exam_type', $src->id, $existing->id, 'skipped', 'already exists');
                $counts['skipped']++;
                continue;
            }

            $new = $src->replicate(['academic_year_id', 'exam_term_id']);
            $new->academic_year_id = $targetYear->id;
            $new->exam_term_id     = $termMap[$src->exam_term_id];
            $new->save();

            $map[$src->id] = $new->id;
            $run->logItem('structure', 'exam_type', $src->id, $new->id, 'success');
            $counts['created']++;
        }

        return [$counts, $map];
    }

    // ---------- Fee structures ----------

    private function cloneFeeStructures(RolloverRun $run, School $school, AcademicYear $sourceYear, AcademicYear $targetYear): array
    {
        $counts = ['created' => 0, 'skipped' => 0, 'failed' => 0];
        $map    = [];

        $rows = FeeStructure::where('school_id', $school->id)
            ->where('academic_year_id', $sourceYear->id)
            ->get();

        foreach ($rows as $src) {
            $existing = FeeStructure::where('school_id', $school->id)
                ->where('academic_year_id', $targetYear->id)
                ->where('class_id', $src->class_id)
                ->where('fee_head_id', $src->fee_head_id)
                ->where('term', $src->term)
                ->first();

            if ($existing) {
                $map[$src->id] = $existing->id;
                $run->logItem('structure', 'fee_structure', $src->id, $existing->id, 'skipped', 'already exists');
                $counts['skipped']++;
                continue;
            }

            $new = $src->replicate(['academic_year_id', 'due_date']);
            $new->academic_year_id = $targetYear->id;
            $new->due_date = $this->shiftDateYear($src->due_date, $sourceYear, $targetYear);
            $new->save();

            $map[$src->id] = $new->id;
            $run->logItem('structure', 'fee_structure', $src->id, $new->id, 'success');
            $counts['created']++;
        }

        return [$counts, $map];
    }

    // ---------- Fee concessions (student-scoped; carry only if student also rolls over) ----------

    private function cloneFeeConcessions(RolloverRun $run, School $school, AcademicYear $sourceYear, AcademicYear $targetYear): array
    {
        $counts = ['created' => 0, 'skipped' => 0, 'failed' => 0];

        $rows = FeeConcession::where('school_id', $school->id)
            ->where('academic_year_id', $sourceYear->id)
            ->where('is_active', true)
            ->get();

        foreach ($rows as $src) {
            $hasTargetEnrolment = DB::table('student_academic_histories')
                ->where('student_id', $src->student_id)
                ->where('academic_year_id', $targetYear->id)
                ->exists();

            if (!$hasTargetEnrolment) {
                $run->logItem('structure', 'fee_concession', $src->id, null, 'skipped', 'student not enrolled in target year');
                $counts['skipped']++;
                continue;
            }

            $existing = FeeConcession::where('school_id', $school->id)
                ->where('academic_year_id', $targetYear->id)
                ->where('student_id', $src->student_id)
                ->where('name', $src->name)
                ->first();

            if ($existing) {
                $run->logItem('structure', 'fee_concession', $src->id, $existing->id, 'skipped', 'already exists');
                $counts['skipped']++;
                continue;
            }

            $new = $src->replicate(['academic_year_id']);
            $new->academic_year_id = $targetYear->id;
            $new->save();

            $run->logItem('structure', 'fee_concession', $src->id, $new->id, 'success');
            $counts['created']++;
        }

        return $counts;
    }

    // ---------- Helpers ----------

    private function shiftDateYear(mixed $date, AcademicYear $sourceYear, AcademicYear $targetYear): ?string
    {
        if (!$date || !$sourceYear->start_date || !$targetYear->start_date) {
            return $date ? \Carbon\Carbon::parse($date)->toDateString() : null;
        }

        $deltaDays = \Carbon\Carbon::parse($sourceYear->start_date)
            ->diffInDays(\Carbon\Carbon::parse($targetYear->start_date), false);

        return \Carbon\Carbon::parse($date)->addDays((int) $deltaDays)->toDateString();
    }

    private function mergeCounts(array $a, array $b): array
    {
        return [
            'created' => ($a['created'] ?? 0) + ($b['created'] ?? 0),
            'skipped' => ($a['skipped'] ?? 0) + ($b['skipped'] ?? 0),
            'failed'  => ($a['failed'] ?? 0) + ($b['failed'] ?? 0),
        ];
    }
}
