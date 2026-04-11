<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\School;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\Attendance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceSeeder extends Seeder
{
    /**
     * Seed realistic attendance data for all students in the current academic year.
     *
     * Strategy:
     *  - Covers every weekday from the later of:
     *      (a) academic year start date  or
     *      (b) June 1st of the same calendar year  (school typically starts June)
     *    up to today (or academic year end if earlier).
     *  - Each student is assigned a random "attendance profile":
     *      Excellent  (90-98% present, rare late/absent)
     *      Good       (80-89% present, moderate late)
     *      Average    (70-79% present, some absents/leaves)
     *      Poor       (<70%  present, frequent absents)
     *  - Weekends are skipped automatically.
     *  - Uses updateOrCreate to be safe to re-run.
     */
    public function run(): void
    {
        $school = School::first();
        if (! $school) {
            $this->command->error('No school found. Seed basic setup first.');
            return;
        }

        $academicYear = AcademicYear::where('school_id', $school->id)
            ->where('is_current', true)
            ->first()
            ?? AcademicYear::where('school_id', $school->id)->latest()->first();

        if (! $academicYear) {
            $this->command->error('No academic year found.');
            return;
        }

        $adminId = \App\Models\User::first()?->id ?? 1;

        // ── Date range ────────────────────────────────────────────────────────
        // Start from June 1 of the academic year start year OR ay.start_date, whichever is later
        $ayStart = $academicYear->start_date
            ? Carbon::parse($academicYear->start_date)
            : Carbon::create(Carbon::now()->year, 6, 1);

        // Academic year typically starts June — use max(ayStart, Jun 1)
        $defaultStart = Carbon::create($ayStart->year, 6, 1);
        $seedFrom     = $ayStart->greaterThan($defaultStart) ? $ayStart : $defaultStart;

        $ayEnd   = $academicYear->end_date
            ? Carbon::parse($academicYear->end_date)
            : Carbon::create($ayStart->year + 1, 3, 31);   // March 31 next year

        $seedTo  = $ayEnd->isFuture() ? Carbon::today() : $ayEnd;

        if ($seedFrom->greaterThan($seedTo)) {
            // Fallback: seed last 90 weekdays from today
            $seedFrom = Carbon::today()->subDays(130);
            $seedTo   = Carbon::today();
        }

        $this->command->info("Seeding attendance from {$seedFrom->toDateString()} to {$seedTo->toDateString()}...");

        // ── Profiles (weights must sum to 100) ────────────────────────────────
        $profiles = [
            'excellent' => [
                'weight'   => 35,
                'weights'  => ['present'=>88, 'late'=>5, 'half_day'=>4, 'absent'=>2, 'leave'=>1],
            ],
            'good' => [
                'weight'   => 35,
                'weights'  => ['present'=>78, 'late'=>8, 'half_day'=>4, 'absent'=>7, 'leave'=>3],
            ],
            'average' => [
                'weight'   => 20,
                'weights'  => ['present'=>65, 'late'=>8, 'half_day'=>5, 'absent'=>15, 'leave'=>7],
            ],
            'poor' => [
                'weight'   => 10,
                'weights'  => ['present'=>50, 'late'=>10, 'half_day'=>5, 'absent'=>27, 'leave'=>8],
            ],
        ];

        // ── Load all weekdays in the range ────────────────────────────────────
        $weekdays = [];
        $period   = CarbonPeriod::create($seedFrom, '1 day', $seedTo);
        foreach ($period as $day) {
            if (! $day->isWeekend()) {
                $weekdays[] = $day->toDateString();
            }
        }

        $this->command->info('Total weekdays to seed: ' . count($weekdays));

        // ── Seed per student ──────────────────────────────────────────────────
        $students = Student::where('school_id', $school->id)->get();
        $this->command->info("Students found: " . $students->count());

        $bar = $this->command->getOutput()->createProgressBar($students->count());
        $bar->start();

        foreach ($students as $student) {
            // Get class/section from academic history for this year
            $history = StudentAcademicHistory::where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->first();

            if (! $history) {
                $bar->advance();
                continue;
            }

            // Assign a random profile to this student (deterministic per student using id)
            $profile = $this->pickProfile($student->id, $profiles);
            $statusWeights = $profile['weights'];

            // Build weighted status pool
            $pool = [];
            foreach ($statusWeights as $status => $weight) {
                for ($i = 0; $i < $weight; $i++) {
                    $pool[] = $status;
                }
            }

            // Delete old records first to allow re-run cleanly
            Attendance::where('school_id', $school->id)
                ->where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->delete();

            // Build batch insert array
            $rows = [];
            foreach ($weekdays as $date) {
                $rows[] = [
                    'school_id'        => $school->id,
                    'academic_year_id' => $academicYear->id,
                    'student_id'       => $student->id,
                    'class_id'         => $history->class_id,
                    'section_id'       => $history->section_id,
                    'date'             => $date,
                    'status'           => $pool[array_rand($pool)],
                    'remarks'          => null,
                    'marked_by'        => $adminId,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }

            // Batch insert in chunks of 100 for performance
            foreach (array_chunk($rows, 100) as $chunk) {
                Attendance::insert($chunk);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info('✅ Attendance seeding complete!');

        // ── Summary output ────────────────────────────────────────────────────
        $total   = Attendance::where('school_id', $school->id)->where('academic_year_id', $academicYear->id)->count();
        $present = Attendance::where('school_id', $school->id)->where('academic_year_id', $academicYear->id)->where('status', 'present')->count();
        $absent  = Attendance::where('school_id', $school->id)->where('academic_year_id', $academicYear->id)->where('status', 'absent')->count();
        $late    = Attendance::where('school_id', $school->id)->where('academic_year_id', $academicYear->id)->where('status', 'late')->count();

        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Total Records',  number_format($total)],
                ['Present',        number_format($present) . ' (' . ($total ? round($present/$total*100,1) : 0) . '%)'],
                ['Absent',         number_format($absent)  . ' (' . ($total ? round($absent/$total*100,1)  : 0) . '%)'],
                ['Late',           number_format($late)    . ' (' . ($total ? round($late/$total*100,1)    : 0) . '%)'],
                ['Students Done',  $students->count()],
                ['Weekdays Seeded', count($weekdays)],
            ]
        );
    }

    /**
     * Pick an attendance profile deterministically for a student based on student id.
     * Uses weighted random based on profile weights.
     */
    private function pickProfile(int $studentId, array $profiles): array
    {
        // Build weighted pool of profile keys
        $pool = [];
        foreach ($profiles as $key => $p) {
            for ($i = 0; $i < $p['weight']; $i++) {
                $pool[] = $key;
            }
        }

        // Use student id as seed for determinism (same student always same profile)
        srand($studentId * 997);
        $picked = $pool[array_rand($pool)];
        srand(); // Reset to random

        return $profiles[$picked];
    }
}
