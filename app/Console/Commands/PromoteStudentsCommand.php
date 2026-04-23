<?php

namespace App\Console\Commands;

use App\Models\CourseClass;
use App\Models\RolloverRun;
use App\Models\StudentAcademicHistory;
use App\Services\StudentRolloverService;
use Illuminate\Console\Command;

/**
 * Promote students from a RolloverRun's source year into its target year using
 * an explicit, name-keyed class map. Exists because the default
 * `by_numeric_value` strategy mis-maps JKG/LKG (both share numeric_value=0)
 * and can't disambiguate 10 TH vs 10 TH STATE (both share numeric_value=10).
 *
 * Stage 1 (structure clone) must have been run already — we need its RolloverRun row.
 */
class PromoteStudentsCommand extends Command
{
    protected $signature = 'rollover:promote-students
        {--run= : RolloverRun id (created by Stage 1 of the wizard)}
        {--dry-run : Preview counts without writing}
        {--promote-detained : Also promote students whose source status is detained}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Promote students with an explicit name-keyed class map (handles KG + class 10 ambiguity).';

    /**
     * Source class name => target class name.
     * Classes absent from this map graduate (no target row created).
     */
    private const CLASS_MAP = [
        'JKG'   => 'LKG',
        'LKG'   => 'UKG',
        'UKG'   => '1 STD',
        '1 STD' => '2 ND',
        '2 ND'  => '3 RD',
        '3 RD'  => '4 TH',
        '4 TH'  => '5 TH',
        '5 TH'  => '6 TH',
        '6 TH'  => '7 TH',
        '7 TH'  => '8 TH',
        '8 TH'  => '9 TH',
        '9 TH'  => '10 TH',
        // '10 TH' and '10 TH STATE' intentionally omitted → graduate.
    ];

    public function handle(StudentRolloverService $service): int
    {
        $runId = $this->option('run');
        if (!$runId) {
            $this->error('Missing --run=<id>. Create the run via the wizard (Stage 1) first.');
            return 1;
        }

        $run = RolloverRun::find($runId);
        if (!$run) {
            $this->error("RolloverRun #{$runId} not found.");
            return 1;
        }

        $schoolId   = $run->school_id;
        $sourceYear = $run->sourceYear;
        $targetYear = $run->targetYear;

        $this->info("Run #{$run->id}: {$sourceYear->name} → {$targetYear->name} (school_id={$schoolId})");

        // Resolve class names → ids for this school.
        $classes = CourseClass::where('school_id', $schoolId)->pluck('id', 'name');
        $classIdToName = $classes->flip();

        $explicitMap   = [];
        $missingSource = [];
        $missingTarget = [];
        foreach (self::CLASS_MAP as $srcName => $tgtName) {
            $srcId = $classes[$srcName] ?? null;
            $tgtId = $classes[$tgtName] ?? null;
            if (!$srcId) { $missingSource[] = $srcName; continue; }
            if (!$tgtId) { $missingTarget[] = "{$srcName}→{$tgtName}"; continue; }
            $explicitMap[$srcId] = $tgtId;
        }

        if ($missingSource || $missingTarget) {
            $this->error('Class name lookup failed for this school:');
            if ($missingSource) $this->line('  Missing source classes: ' . implode(', ', $missingSource));
            if ($missingTarget) $this->line('  Missing target classes: ' . implode(', ', $missingTarget));
            $this->line('  Check CourseClass.name values match the map exactly (case-sensitive, spaces included).');
            return 1;
        }

        $this->printMapTable($explicitMap, $classIdToName);

        $sourceCounts = StudentAcademicHistory::where('school_id', $schoolId)
            ->where('academic_year_id', $sourceYear->id)
            ->selectRaw('class_id, count(*) as cnt')
            ->groupBy('class_id')
            ->pluck('cnt', 'class_id');

        $unmappedClasses = $sourceCounts->keys()
            ->reject(fn($cid) => isset($explicitMap[$cid]))
            ->map(fn($cid) => ['name' => $classIdToName[$cid] ?? "class#{$cid}", 'count' => $sourceCounts[$cid]])
            ->values();

        if ($unmappedClasses->isNotEmpty()) {
            $this->warn('The following source-year classes are NOT in the map — their students will be marked graduated:');
            $this->table(['Class', 'Students'], $unmappedClasses->map(fn($r) => [$r['name'], $r['count']])->all());
        }

        $dryRun = (bool) $this->option('dry-run');

        if (!$dryRun && !$this->option('force')) {
            $total = $sourceCounts->sum();
            if (!$this->confirm("Promote {$total} student(s) from {$sourceYear->name} into {$targetYear->name}? This writes to the database.")) {
                $this->line('Aborted.');
                return 1;
            }
        }

        $result = $service->execute($run, [
            'strategy'         => StudentRolloverService::STRATEGY_EXPLICIT,
            'class_map'        => $explicitMap,
            'promote_detained' => (bool) $this->option('promote-detained'),
            'dry_run'          => $dryRun,
        ]);

        $this->newLine();
        $this->info($dryRun ? 'Dry-run summary (no writes):' : 'Promotion summary:');
        $this->table(
            ['Outcome', 'Count'],
            [
                ['Promoted',  $result['promoted']],
                ['Graduated', $result['graduated']],
                ['Detained',  $result['detained']],
                ['Skipped',   $result['skipped']],
                ['Failed',    $result['failed']],
            ]
        );

        return $result['failed'] > 0 ? 1 : 0;
    }

    private function printMapTable(array $explicitMap, \Illuminate\Support\Collection $classIdToName): void
    {
        $rows = [];
        foreach ($explicitMap as $srcId => $tgtId) {
            $rows[] = [$classIdToName[$srcId] ?? "#{$srcId}", '→', $classIdToName[$tgtId] ?? "#{$tgtId}"];
        }
        $this->line('Class map:');
        $this->table(['From', '', 'To'], $rows);
    }
}
