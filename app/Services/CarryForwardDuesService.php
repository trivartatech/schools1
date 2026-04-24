<?php

namespace App\Services;

use App\Enums\FeePaymentStatus;
use App\Enums\RolloverState;
use App\Models\FeePayment;
use App\Models\RolloverRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Carries unpaid fee balance from the source year into the target year.
 *
 * For every student enrolled in the target year, we look at their source-year
 * fee_payments whose balance > 0 (statuses: due, partial). Balances are grouped
 * by fee_head_id and a single carry-forward FeePayment row is inserted in the
 * target year per group, flagged with is_carry_forward=true and pointing back
 * at the first source payment in that group via source_payment_id.
 *
 * The service is idempotent per run: a carry-forward row is linked to
 * rollover_run_id, so a second execute() for the same run short-circuits
 * when it sees existing rows.
 */
class CarryForwardDuesService
{
    /**
     * @return array{
     *   dry_run: bool,
     *   students_scanned: int,
     *   students_with_dues: int,
     *   rows_created: int,
     *   total_amount: string,
     *   skipped: int,
     * }
     */
    public function execute(RolloverRun $run, array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);

        $school     = $run->school;
        $sourceYear = $run->sourceYear;
        $targetYear = $run->targetYear;

        if (!$dryRun) {
            $run->transitionTo(RolloverState::FeesRunning);
        }

        $result = [
            'dry_run'            => $dryRun,
            'students_scanned'   => 0,
            'students_with_dues' => 0,
            'rows_created'       => 0,
            'total_amount'       => '0.00',
            'skipped'            => 0,
        ];

        try {
            $alreadyRan = FeePayment::where('school_id', $school->id)
                ->where('academic_year_id', $targetYear->id)
                ->where('rollover_run_id', $run->id)
                ->exists();

            if ($alreadyRan && !$dryRun) {
                $run->transitionTo(RolloverState::FeesDone);
                return array_merge($result, ['skipped' => 1]);
            }

            $studentIds = DB::table('student_academic_histories')
                ->where('school_id', $school->id)
                ->where('academic_year_id', $targetYear->id)
                ->pluck('student_id')
                ->unique()
                ->values();

            $result['students_scanned'] = $studentIds->count();

            $paymentDate = $targetYear->start_date
                ? Carbon::parse($targetYear->start_date)->toDateString()
                : now()->toDateString();

            $totalAmount = 0.0;

            if ($dryRun) {
                foreach ($studentIds as $studentId) {
                    $groups = $this->outstandingGroupsFor($school->id, $sourceYear->id, (int) $studentId);
                    if ($groups->isEmpty()) continue;

                    $result['students_with_dues']++;
                    $result['rows_created'] += $groups->count();
                    foreach ($groups as $g) {
                        $totalAmount += (float) $g->outstanding;
                    }
                }
                $result['total_amount'] = number_format($totalAmount, 2, '.', '');
                return $result;
            }

            DB::transaction(function () use ($run, $school, $sourceYear, $targetYear, $studentIds, $paymentDate, &$result, &$totalAmount) {
                foreach ($studentIds as $studentId) {
                    $groups = $this->outstandingGroupsFor($school->id, $sourceYear->id, (int) $studentId);
                    if ($groups->isEmpty()) continue;

                    $result['students_with_dues']++;

                    foreach ($groups as $g) {
                        $firstSourcePayment = FeePayment::where('school_id', $school->id)
                            ->where('academic_year_id', $sourceYear->id)
                            ->where('student_id', $studentId)
                            ->where('fee_head_id', $g->fee_head_id)
                            ->whereIn('status', [
                                FeePaymentStatus::Due->value,
                                FeePaymentStatus::Partial->value,
                            ])
                            ->orderBy('id')
                            ->first();

                        $amount = number_format((float) $g->outstanding, 2, '.', '');

                        $new = FeePayment::create([
                            'school_id'         => $school->id,
                            'student_id'        => $studentId,
                            'academic_year_id'  => $targetYear->id,
                            'fee_head_id'       => $g->fee_head_id,
                            'fee_structure_id'  => null,
                            'amount_due'        => $amount,
                            'amount_paid'       => 0,
                            'discount'          => 0,
                            'fine'              => 0,
                            'term'              => 'annual',
                            'payment_date'      => $paymentDate,
                            'payment_mode'      => 'cash',
                            'status'            => FeePaymentStatus::Due,
                            'is_carry_forward'  => true,
                            'source_payment_id' => $firstSourcePayment?->id,
                            'source_year_id'    => $sourceYear->id,
                            'rollover_run_id'   => $run->id,
                            'remarks'           => "Carried forward from {$sourceYear->name}",
                        ]);

                        $result['rows_created']++;
                        $totalAmount += (float) $amount;

                        $run->logItem('fees', 'carry_forward', $firstSourcePayment?->id, $new->id, 'success', null, [
                            'student_id'  => $studentId,
                            'fee_head_id' => $g->fee_head_id,
                            'amount'      => $amount,
                        ]);
                    }
                }
            });

            $result['total_amount'] = number_format($totalAmount, 2, '.', '');
            $run->setStat('fees', $result);
            $run->transitionTo(RolloverState::FeesDone);

            return $result;
        } catch (\Throwable $e) {
            $run->error = $e->getMessage();
            $run->transitionTo(RolloverState::Failed);
            throw $e;
        }
    }

    /**
     * Outstanding-by-fee-head summary for a single student's source-year payments.
     * Returns a collection of rows { fee_head_id, outstanding, count }.
     *
     * IMPORTANT: carry-forward rows in the source year ARE included. This lets
     * unpaid balances cascade across multiple years (e.g. 2024-25 dues → 2025-26
     * carry-forward row → 2026-27 carry-forward row, if still unpaid). Excluding
     * them here would silently drop multi-year arrears.
     */
    private function outstandingGroupsFor(int $schoolId, int $sourceYearId, int $studentId)
    {
        return FeePayment::query()
            ->selectRaw('fee_head_id, SUM(balance) as outstanding, COUNT(*) as count')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $sourceYearId)
            ->where('student_id', $studentId)
            ->where('balance', '>', 0)
            ->whereIn('status', [
                FeePaymentStatus::Due->value,
                FeePaymentStatus::Partial->value,
            ])
            ->groupBy('fee_head_id')
            ->havingRaw('SUM(balance) > 0')
            ->get();
    }

    /**
     * Carry outstanding balances for a specific set of students (typically the
     * batch just promoted via the UI wizard). Idempotent per (student, fee_head)
     * — a re-run under the same RolloverRun will skip groups that already have
     * a carry-forward row.
     *
     * Does NOT transition the RolloverRun state.
     *
     * @param  int[] $studentIds
     * @return array{students_with_dues:int, rows_created:int, total_amount:string, skipped:int}
     */
    public function forStudents(RolloverRun $run, array $studentIds): array
    {
        $school     = $run->school;
        $sourceYear = $run->sourceYear;
        $targetYear = $run->targetYear;

        $result = [
            'students_with_dues' => 0,
            'rows_created'       => 0,
            'total_amount'       => '0.00',
            'skipped'            => 0,
        ];

        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        if (empty($studentIds)) {
            return $result;
        }

        $paymentDate = $targetYear->start_date
            ? Carbon::parse($targetYear->start_date)->toDateString()
            : now()->toDateString();

        $totalAmount = 0.0;

        DB::transaction(function () use (
            $run, $school, $sourceYear, $targetYear, $studentIds, $paymentDate,
            &$result, &$totalAmount
        ) {
            foreach ($studentIds as $studentId) {
                $groups = $this->outstandingGroupsFor($school->id, $sourceYear->id, (int) $studentId);
                if ($groups->isEmpty()) continue;

                $createdForStudent = 0;

                foreach ($groups as $g) {
                    // Idempotency: skip if this run already wrote a carry-forward
                    // for the same (student, fee_head).
                    $already = FeePayment::where('school_id', $school->id)
                        ->where('academic_year_id', $targetYear->id)
                        ->where('student_id', $studentId)
                        ->where('fee_head_id', $g->fee_head_id)
                        ->where('rollover_run_id', $run->id)
                        ->exists();

                    if ($already) {
                        $result['skipped']++;
                        continue;
                    }

                    $firstSourcePayment = FeePayment::where('school_id', $school->id)
                        ->where('academic_year_id', $sourceYear->id)
                        ->where('student_id', $studentId)
                        ->where('fee_head_id', $g->fee_head_id)
                        ->whereIn('status', [
                            FeePaymentStatus::Due->value,
                            FeePaymentStatus::Partial->value,
                        ])
                        ->orderBy('id')
                        ->first();

                    $amount = number_format((float) $g->outstanding, 2, '.', '');

                    $new = FeePayment::create([
                        'school_id'         => $school->id,
                        'student_id'        => $studentId,
                        'academic_year_id'  => $targetYear->id,
                        'fee_head_id'       => $g->fee_head_id,
                        'fee_structure_id'  => null,
                        'amount_due'        => $amount,
                        'amount_paid'       => 0,
                        'discount'          => 0,
                        'fine'              => 0,
                        'term'              => 'annual',
                        'payment_date'      => $paymentDate,
                        'payment_mode'      => 'cash',
                        'status'            => FeePaymentStatus::Due,
                        'is_carry_forward'  => true,
                        'source_payment_id' => $firstSourcePayment?->id,
                        'source_year_id'    => $sourceYear->id,
                        'rollover_run_id'   => $run->id,
                        'remarks'           => "Carried forward from {$sourceYear->name}",
                    ]);

                    $result['rows_created']++;
                    $createdForStudent++;
                    $totalAmount += (float) $amount;

                    $run->logItem('fees', 'carry_forward', $firstSourcePayment?->id, $new->id, 'success', null, [
                        'student_id'  => $studentId,
                        'fee_head_id' => $g->fee_head_id,
                        'amount'      => $amount,
                        'manual'      => true,
                    ]);
                }

                if ($createdForStudent > 0) {
                    $result['students_with_dues']++;
                }
            }
        });

        $result['total_amount'] = number_format($totalAmount, 2, '.', '');

        // Roll into run stats for the UI.
        $prev = $run->stats['fees_manual'] ?? [
            'students_with_dues' => 0, 'rows_created' => 0, 'total_amount' => 0.0, 'skipped' => 0,
        ];
        $run->setStat('fees_manual', [
            'students_with_dues' => $prev['students_with_dues'] + $result['students_with_dues'],
            'rows_created'       => $prev['rows_created']       + $result['rows_created'],
            'total_amount'       => number_format((float) $prev['total_amount'] + $totalAmount, 2, '.', ''),
            'skipped'            => $prev['skipped']            + $result['skipped'],
        ]);

        return $result;
    }

    /**
     * Finalize the run: mark all phases complete and freeze the source year.
     */
    public function finalize(RolloverRun $run, bool $freezeSource = true): void
    {
        DB::transaction(function () use ($run, $freezeSource) {
            if ($freezeSource && $run->sourceYear) {
                $run->sourceYear->update(['status' => 'frozen']);
            }
            $run->transitionTo(RolloverState::Finalized);
        });
    }
}
