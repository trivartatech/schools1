<?php

/**
 * Repair the carry-forward chain so every row in the current year is sourced
 * from the immediately preceding year (2024-25 → 2025-26 → 2026-27).
 *
 * Steps:
 *   1. DELETE the direct-jump backfill rows (2024-25 → 2026-27, amount_paid=0).
 *   2. DELETE the original sparse 2025-26 → 2026-27 rows that were created
 *      before the cascade fix (amount_paid=0) so the fresh ones replace them.
 *   3. CREATE carry-forward rows 2024-25 → 2025-26 (one per (student, head)
 *      aggregating all unpaid balance).
 *   4. CREATE carry-forward rows 2025-26 → 2026-27 (includes original
 *      2025-26 unpaid + carried-from-2024-25 unpaid, aggregated per head).
 *
 * Paid carry-forward rows are NEVER deleted — safe by design.
 *
 * Usage:
 *   php artisan tinker --execute="require base_path('scripts/fix_carry_forward_cascade.php');"
 */

use Illuminate\Support\Facades\DB;

$schoolId = 1;

$year2024 = \App\Models\AcademicYear::where('school_id', $schoolId)->where('name', '2024-25')->first();
$year2025 = \App\Models\AcademicYear::where('school_id', $schoolId)->where('name', '2025-26')->first();
$year2026 = \App\Models\AcademicYear::where('school_id', $schoolId)->where('name', '2026-27')->first();

if (!$year2024 || !$year2025 || !$year2026) {
    echo "ERROR: one of the academic years is missing.\n";
    return;
}

echo "Years: 2024-25={$year2024->id}, 2025-26={$year2025->id}, 2026-27={$year2026->id}\n\n";

/* ─── STEP 1 & 2: clean up untouched carry-forward rows in 2026-27 ─── */
$deleted2026 = \App\Models\FeePayment::where('school_id', $schoolId)
    ->where('academic_year_id', $year2026->id)
    ->where('is_carry_forward', true)
    ->where('amount_paid', 0)
    ->where('discount', 0)
    ->delete();
echo "Step 1+2: Deleted {$deleted2026} untouched carry-forward rows in 2026-27.\n";

/* ─── STEP 3: build carry-forward rows 2024-25 → 2025-26 ─── */
$unpaid2024 = \App\Models\FeePayment::where('school_id', $schoolId)
    ->where('academic_year_id', $year2024->id)
    ->whereIn('status', ['due', 'partial'])
    ->where('balance', '>', 0)
    ->selectRaw('student_id, fee_head_id, SUM(balance) as outstanding')
    ->groupBy('student_id', 'fee_head_id')
    ->havingRaw('SUM(balance) > 0')
    ->get();

$created2025 = 0;
$skipped2025 = 0;
foreach ($unpaid2024 as $u) {
    $exists = \App\Models\FeePayment::where('school_id', $schoolId)
        ->where('student_id', $u->student_id)
        ->where('academic_year_id', $year2025->id)
        ->where('fee_head_id', $u->fee_head_id)
        ->where('source_year_id', $year2024->id)
        ->where('is_carry_forward', true)
        ->exists();
    if ($exists) { $skipped2025++; continue; }

    $srcPayment = \App\Models\FeePayment::where('school_id', $schoolId)
        ->where('student_id', $u->student_id)
        ->where('academic_year_id', $year2024->id)
        ->where('fee_head_id', $u->fee_head_id)
        ->whereIn('status', ['due', 'partial'])
        ->orderBy('id')->first();

    \App\Models\FeePayment::create([
        'school_id'         => $schoolId,
        'student_id'        => $u->student_id,
        'academic_year_id'  => $year2025->id,
        'fee_head_id'       => $u->fee_head_id,
        'amount_due'        => number_format((float) $u->outstanding, 2, '.', ''),
        'amount_paid'       => 0,
        'discount'          => 0,
        'fine'              => 0,
        'term'              => 'annual',
        'payment_date'      => \Illuminate\Support\Carbon::parse($year2025->start_date ?? now())->toDateString(),
        'payment_mode'      => 'cash',
        'status'            => 'due',
        'is_carry_forward'  => true,
        'source_payment_id' => $srcPayment?->id,
        'source_year_id'    => $year2024->id,
        'remarks'           => "Carried forward from {$year2024->name}",
    ]);
    $created2025++;
}
echo "Step 3: 2024-25 → 2025-26  | Created: {$created2025} | Skipped: {$skipped2025}\n";

/* ─── STEP 4: build carry-forward rows 2025-26 → 2026-27 ─── */
/* This aggregates ORIGINAL 2025-26 unpaid + CARRIED 2024-25 rows            */
/* into one row per (student, fee_head) in 2026-27.                          */
$unpaid2025 = \App\Models\FeePayment::where('school_id', $schoolId)
    ->where('academic_year_id', $year2025->id)
    ->whereIn('status', ['due', 'partial'])
    ->where('balance', '>', 0)
    ->selectRaw('student_id, fee_head_id, SUM(balance) as outstanding')
    ->groupBy('student_id', 'fee_head_id')
    ->havingRaw('SUM(balance) > 0')
    ->get();

$created2026 = 0;
$skipped2026 = 0;
foreach ($unpaid2025 as $u) {
    $exists = \App\Models\FeePayment::where('school_id', $schoolId)
        ->where('student_id', $u->student_id)
        ->where('academic_year_id', $year2026->id)
        ->where('fee_head_id', $u->fee_head_id)
        ->where('source_year_id', $year2025->id)
        ->where('is_carry_forward', true)
        ->exists();
    if ($exists) { $skipped2026++; continue; }

    $srcPayment = \App\Models\FeePayment::where('school_id', $schoolId)
        ->where('student_id', $u->student_id)
        ->where('academic_year_id', $year2025->id)
        ->where('fee_head_id', $u->fee_head_id)
        ->whereIn('status', ['due', 'partial'])
        ->orderBy('id')->first();

    \App\Models\FeePayment::create([
        'school_id'         => $schoolId,
        'student_id'        => $u->student_id,
        'academic_year_id'  => $year2026->id,
        'fee_head_id'       => $u->fee_head_id,
        'amount_due'        => number_format((float) $u->outstanding, 2, '.', ''),
        'amount_paid'       => 0,
        'discount'          => 0,
        'fine'              => 0,
        'term'              => 'annual',
        'payment_date'      => \Illuminate\Support\Carbon::parse($year2026->start_date ?? now())->toDateString(),
        'payment_mode'      => 'cash',
        'status'            => 'due',
        'is_carry_forward'  => true,
        'source_payment_id' => $srcPayment?->id,
        'source_year_id'    => $year2025->id,
        'remarks'           => "Carried forward from {$year2025->name}",
    ]);
    $created2026++;
}
echo "Step 4: 2025-26 → 2026-27  | Created: {$created2026} | Skipped: {$skipped2026}\n\n";

echo "Done.\n";
