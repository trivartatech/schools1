<?php

namespace App\Services;

use App\Enums\RolloverState;
use App\Models\AcademicYear;
use App\Models\CourseClass;
use App\Models\RolloverRun;
use App\Models\School;
use App\Models\Section;
use App\Models\StudentAcademicHistory;
use Illuminate\Support\Facades\DB;

/**
 * Promotes every student enrolled in the source year into the target year by
 * creating a new StudentAcademicHistory row per student, then marking the
 * source row 'promoted' / 'detained' / 'graduated' as appropriate.
 *
 * Strategy:
 *   - Build a class_map: source_class_id => target_class_id (either supplied
 *     explicitly in $options['class_map'] or derived from numeric_value + 1).
 *   - For each source history row:
 *       • if class not in map    → 'graduated'  (no target row)
 *       • if status = 'detained' → 'detained'   (no target row)  unless options.promote_detained
 *       • else                   → 'promoted'   (creates target row)
 *   - Section mapping: same-name section under the target class; fall back to
 *     leaving section null. Overridable per-class via $options['section_map'].
 *
 * Dry-run mode previews counts without writing.
 */
class StudentRolloverService
{
    public const STRATEGY_NUMERIC    = 'by_numeric_value';
    public const STRATEGY_EXPLICIT   = 'explicit_map';

    /**
     * @return array{
     *   dry_run: bool,
     *   promoted: int,
     *   detained: int,
     *   graduated: int,
     *   skipped: int,
     *   failed: int,
     *   class_map: array<int,int>,
     * }
     */
    public function execute(RolloverRun $run, array $options = []): array
    {
        $dryRun            = (bool) ($options['dry_run'] ?? false);
        $strategy          = $options['strategy']          ?? self::STRATEGY_NUMERIC;
        $explicitClassMap  = $options['class_map']         ?? [];
        $sectionMap        = $options['section_map']       ?? [];
        $promoteDetained   = (bool) ($options['promote_detained'] ?? false);
        $defaultStatus     = $options['new_status_default'] ?? 'current';

        $school     = $run->school;
        $sourceYear = $run->sourceYear;
        $targetYear = $run->targetYear;

        if (!$dryRun) {
            $run->transitionTo(RolloverState::StudentsRunning);
        }

        $result = [
            'dry_run'   => $dryRun,
            'promoted'  => 0,
            'detained'  => 0,
            'graduated' => 0,
            'skipped'   => 0,
            'failed'    => 0,
            'class_map' => [],
        ];

        try {
            $classMap = $this->buildClassMap($school, $strategy, $explicitClassMap);
            $result['class_map'] = $classMap;

            $rows = StudentAcademicHistory::where('school_id', $school->id)
                ->where('academic_year_id', $sourceYear->id)
                ->get();

            if ($dryRun) {
                foreach ($rows as $row) {
                    $this->classifyOutcome($row, $classMap, $promoteDetained, $result);
                }
                return $result;
            }

            DB::transaction(function () use ($rows, $run, $school, $targetYear, $classMap, $sectionMap, $promoteDetained, $defaultStatus, &$result) {
                foreach ($rows as $row) {
                    try {
                        $this->processRow($run, $school, $targetYear, $row, $classMap, $sectionMap, $promoteDetained, $defaultStatus, $result);
                    } catch (\Throwable $e) {
                        $result['failed']++;
                        $run->logItem('students', 'student_history', $row->id, null, 'failed', $e->getMessage());
                    }
                }
            });

            $run->setStat('students', $result);
            $run->transitionTo(RolloverState::StudentsDone);

            return $result;
        } catch (\Throwable $e) {
            $run->error = $e->getMessage();
            $run->transitionTo(RolloverState::Failed);
            throw $e;
        }
    }

    /**
     * Build source_class_id => target_class_id map.
     */
    private function buildClassMap(School $school, string $strategy, array $explicitMap): array
    {
        if ($strategy === self::STRATEGY_EXPLICIT) {
            $classes = CourseClass::where('school_id', $school->id)
                ->whereIn('id', array_values(array_filter($explicitMap)))
                ->pluck('id')->all();

            $clean = [];
            foreach ($explicitMap as $srcId => $tgtId) {
                $srcId = (int) $srcId;
                $tgtId = (int) $tgtId;
                if ($tgtId && in_array($tgtId, $classes, true)) {
                    $clean[$srcId] = $tgtId;
                }
            }
            return $clean;
        }

        $classes = CourseClass::where('school_id', $school->id)->get();
        $byNumeric = [];
        foreach ($classes as $cls) {
            if ($cls->numeric_value !== null) {
                $byNumeric[(int) $cls->numeric_value][] = $cls->id;
            }
        }

        $map = [];
        foreach ($classes as $cls) {
            if ($cls->numeric_value === null) continue;
            $nextValue = (int) $cls->numeric_value + 1;
            $candidates = $byNumeric[$nextValue] ?? null;
            if (!$candidates) continue;
            $map[$cls->id] = $candidates[0];
        }

        return $map;
    }

    private function classifyOutcome(StudentAcademicHistory $row, array $classMap, bool $promoteDetained, array &$result): string
    {
        if ($row->status === 'graduated') {
            $result['skipped']++;
            return 'skipped';
        }
        if ($row->status === 'detained' && !$promoteDetained) {
            $result['detained']++;
            return 'detained';
        }
        if (!isset($classMap[$row->class_id])) {
            $result['graduated']++;
            return 'graduated';
        }
        $result['promoted']++;
        return 'promoted';
    }

    private function processRow(
        RolloverRun $run,
        School $school,
        AcademicYear $targetYear,
        StudentAcademicHistory $row,
        array $classMap,
        array $sectionMap,
        bool $promoteDetained,
        string $defaultStatus,
        array &$result
    ): void {
        $existing = StudentAcademicHistory::where('student_id', $row->student_id)
            ->where('academic_year_id', $targetYear->id)
            ->first();

        if ($existing) {
            $run->logItem('students', 'student_history', $row->id, $existing->id, 'skipped', 'already has target-year row');
            $result['skipped']++;
            return;
        }

        $outcome = $this->classifyOutcome($row, $classMap, $promoteDetained, $counts = ['skipped' => 0, 'detained' => 0, 'graduated' => 0, 'promoted' => 0]);

        if ($outcome === 'skipped') {
            $result['skipped']++;
            $run->logItem('students', 'student_history', $row->id, null, 'skipped', 'source already graduated');
            return;
        }

        if ($outcome === 'graduated') {
            $row->update(['status' => 'graduated']);
            $result['graduated']++;
            $run->logItem('students', 'student_history', $row->id, null, 'success', 'no next class — graduated');
            return;
        }

        if ($outcome === 'detained') {
            $row->update(['status' => 'detained']);
            $result['detained']++;
            $run->logItem('students', 'student_history', $row->id, null, 'success', 'detained — no target row created');
            return;
        }

        $targetClassId   = $classMap[$row->class_id];
        $targetSectionId = $this->resolveTargetSection($row, $targetClassId, $sectionMap);

        $new = StudentAcademicHistory::create([
            'school_id'        => $school->id,
            'student_id'       => $row->student_id,
            'academic_year_id' => $targetYear->id,
            'class_id'         => $targetClassId,
            'section_id'       => $targetSectionId,
            'status'           => $defaultStatus,
            'enrollment_type'  => 'Regular',
            'student_type'     => 'Old Student',
            'roll_no'          => null,
        ]);

        $row->update(['status' => 'promoted']);
        $result['promoted']++;
        $run->logItem('students', 'student_history', $row->id, $new->id, 'success', null, [
            'from_class_id'   => $row->class_id,
            'to_class_id'     => $targetClassId,
            'from_section_id' => $row->section_id,
            'to_section_id'   => $targetSectionId,
        ]);
    }

    private function resolveTargetSection(StudentAcademicHistory $row, int $targetClassId, array $sectionMap): ?int
    {
        if (isset($sectionMap[$row->section_id])) {
            return (int) $sectionMap[$row->section_id] ?: null;
        }

        if (!$row->section_id) return null;

        $srcSection = Section::find($row->section_id);
        if (!$srcSection) return null;

        $match = Section::where('course_class_id', $targetClassId)
            ->where('name', $srcSection->name)
            ->first();

        return $match?->id;
    }
}
