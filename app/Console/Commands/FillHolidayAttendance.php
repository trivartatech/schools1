<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\School;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Auto-fill 'holiday' attendance rows on declared holidays.
 *
 * Usage:
 *   php artisan attendance:fill-holidays                 → today only, all schools
 *   php artisan attendance:fill-holidays --date=2026-04-26
 *   php artisan attendance:fill-holidays --school=3 --days=30   → backfill 30 days
 *
 * Idempotent — uses updateOrCreate so re-runs don't duplicate.
 * Won't overwrite existing rows (e.g. a student marked Present on what
 * later got declared a holiday) unless --force is passed.
 */
class FillHolidayAttendance extends Command
{
    protected $signature = 'attendance:fill-holidays
        {--date= : YYYY-MM-DD; defaults to today}
        {--days= : Backfill the last N days instead of just one date}
        {--school= : Limit to a specific school_id}
        {--force : Overwrite existing non-holiday rows on holiday dates}';

    protected $description = 'Mark every active student and staff as holiday on declared school holidays.';

    public function handle(): int
    {
        $endDate    = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $daysBack   = (int) ($this->option('days') ?? 0);
        $startDate  = $daysBack > 0 ? $endDate->copy()->subDays($daysBack - 1) : $endDate->copy();
        $schoolFlag = $this->option('school');
        $force      = (bool) $this->option('force');

        $schools = School::query()
            ->when($schoolFlag, fn($q) => $q->where('id', $schoolFlag))
            ->where('status', 'active')
            ->get();

        if ($schools->isEmpty()) {
            $this->warn('No active schools found.');
            return self::SUCCESS;
        }

        $totalStudents = 0;
        $totalStaff    = 0;
        $totalSkipped  = 0;

        foreach ($schools as $school) {
            $holidayDates = Holiday::where('school_id', $school->id)
                ->where(function ($q) use ($startDate, $endDate) {
                    // A holiday hits the window if its [date, end_date|date] overlaps
                    $q->whereBetween('date', [$startDate, $endDate])
                      ->orWhere(function ($q2) use ($startDate, $endDate) {
                          $q2->whereNotNull('end_date')
                             ->where('date', '<=', $endDate)
                             ->where('end_date', '>=', $startDate);
                      });
                })
                ->get()
                ->flatMap(function ($h) use ($startDate, $endDate) {
                    $from = Carbon::parse($h->date)->max($startDate);
                    $to   = Carbon::parse($h->end_date ?? $h->date)->min($endDate);
                    $dates = collect();
                    for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                        $dates->push($d->toDateString());
                    }
                    return $dates;
                })
                ->unique()
                ->values();

            if ($holidayDates->isEmpty()) {
                continue;
            }

            $this->line("School #{$school->id} {$school->name}: " . $holidayDates->count() . ' holiday day(s) in range');

            // Resolve current academic year for this school (needed for student attendance row)
            $currentYear = \App\Models\AcademicYear::where('school_id', $school->id)
                ->where('is_current', true)->first();

            foreach ($holidayDates as $dateStr) {
                $sStudents = $this->fillStudents($school, $dateStr, $currentYear, $force);
                $sStaff    = $this->fillStaff($school, $dateStr, $force);
                $totalStudents += $sStudents['written'];
                $totalStaff    += $sStaff['written'];
                $totalSkipped  += $sStudents['skipped'] + $sStaff['skipped'];
            }
        }

        $this->info("Done. Wrote {$totalStudents} student row(s) and {$totalStaff} staff row(s). Skipped {$totalSkipped} pre-existing non-holiday row(s).");

        return self::SUCCESS;
    }

    /**
     * Fill student attendance for a single school on a single date.
     * Returns [written => N, skipped => M].
     */
    private function fillStudents(School $school, string $dateStr, $currentYear, bool $force): array
    {
        if (!$currentYear) return ['written' => 0, 'skipped' => 0];

        // Active students with their current class/section
        $rows = StudentAcademicHistory::where('school_id', $school->id)
            ->where('academic_year_id', $currentYear->id)
            ->where('status', 'current')
            ->get(['student_id', 'class_id', 'section_id']);

        if ($rows->isEmpty()) return ['written' => 0, 'skipped' => 0];

        $written = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $school, $currentYear, $dateStr, $force, &$written, &$skipped) {
            foreach ($rows as $r) {
                $existing = Attendance::where('school_id', $school->id)
                    ->where('student_id', $r->student_id)
                    ->where('date', $dateStr)
                    ->first();

                if ($existing && !$force && $existing->status !== 'holiday') {
                    $skipped++;
                    continue;
                }

                if ($existing && $force && $existing->status !== 'holiday') {
                    $this->warn("  --force: overwriting student {$r->student_id} status '{$existing->status}' → 'holiday' on {$dateStr}");
                }

                Attendance::updateOrCreate(
                    [
                        'school_id'  => $school->id,
                        'student_id' => $r->student_id,
                        'date'       => $dateStr,
                    ],
                    [
                        'academic_year_id' => $currentYear->id,
                        'class_id'         => $r->class_id,
                        'section_id'       => $r->section_id,
                        'status'           => 'holiday',
                        'remarks'          => 'Auto: school holiday',
                        'marked_by'        => null,
                    ]
                );
                $written++;
            }
        });

        return ['written' => $written, 'skipped' => $skipped];
    }

    /**
     * Fill staff attendance for a single school on a single date.
     */
    private function fillStaff(School $school, string $dateStr, bool $force): array
    {
        $staffIds = Staff::where('school_id', $school->id)
            ->where('status', 'active')
            ->pluck('id');

        if ($staffIds->isEmpty()) return ['written' => 0, 'skipped' => 0];

        $written = 0;
        $skipped = 0;

        DB::transaction(function () use ($staffIds, $school, $dateStr, $force, &$written, &$skipped) {
            foreach ($staffIds as $staffId) {
                $existing = StaffAttendance::where('school_id', $school->id)
                    ->where('staff_id', $staffId)
                    ->where('date', $dateStr)
                    ->first();

                if ($existing && !$force && $existing->status !== 'holiday') {
                    $skipped++;
                    continue;
                }

                if ($existing && $force && $existing->status !== 'holiday') {
                    $this->warn("  --force: overwriting staff {$staffId} status '{$existing->status}' → 'holiday' on {$dateStr}");
                }

                StaffAttendance::updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'staff_id'  => $staffId,
                        'date'      => $dateStr,
                    ],
                    [
                        'status'    => 'holiday',
                        'remarks'   => 'Auto: school holiday',
                        'marked_by' => null,
                    ]
                );
                $written++;
            }
        });

        return ['written' => $written, 'skipped' => $skipped];
    }
}
