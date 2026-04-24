<?php

/**
 * Rebuild 2026-27 carry-forward rows using ONLY original 2025-26 unpaid
 * balances (not cascaded 2024-25 carries). This matches the policy where
 * each year's carry-forward shows strictly its immediately preceding year's
 * own unpaid fees — no deep cascades.
 *
 * Steps:
 *   1. DELETE all untouched (amount_paid=0, discount=0) carry-forward rows
 *      in 2026-27. Paid/discounted rows are preserved.
 *   2. CREATE one carry-forward row per (student, fee_head) from 2025-26
 *      ORIGINAL unpaid (where is_carry_forward=false in 2025-26).
 *
 * Usage:
 *   php artisan tinker --execute="require base_path('scripts/rebuild_cf_immediate_only.php');"
 */

$schoolId = 1;

$year2025 = \App\Models\AcademicYear::where('school_id', $schoolId)->where('name', '2025-26')->first();
$year2026 = \App\Models\AcademicYear::where('school_id', $schoolId)->where('name', '2026-27')->first();

if (!$year2025 || !$year2026) {
    echo "ERROR: required academic year missing.\n";
    return;
}

echo "Source: 2025-26 (id={$year2025->id})\n";
echo "Target: 2026-27 (id={$year2026->id})\n\n";

/* ── STEP 1: remove old (untouched) carry-forward rows in 2026-27 ── */
$deleted = \App\Models\FeePayment::where('school_id', $schoolId)
    ->where('academic_year_id', $year2026->id)
    ->where('is_carry_forward', true)
    ->where('amount_paid', 0)
    ->where('discount', 0)
    ->delete();
echo "Step 1: Deleted {$deleted} untouched carry-forward rows in 2026-27.\n";

/* ── STEP 2: create CF from 2025-26 ORIGINAL unpaid only ── */
$unpaidOriginal = \App\Models\FeePayment::where('school_id', $schoolId)
    ->where('academic_year_id', $year2025->id)
    ->where('is_carry_forward', false)
    ->whereIn('status', ['due', 'partial'])
    ->where('balance', '>', 0)
    ->selectRaw('student_id, fee_head_id, SUM(balance) as outstanding')
    ->groupBy('student_id', 'fee_head_id')
    ->havingRaw('SUM(balance) > 0')
    ->get();

echo "Step 2: Found " . $unpaidOriginal->count() . " (student, head) groups with unpaid ORIGINAL balance in 2025-26.\n";

$created = 0;
$skipped = 0;
$totalAmount = 0.0;
foreach ($unpaidOriginal as $u) {
    $exists = \App\Models\FeePayment::where('school_id', $schoolId)
        ->where('student_id', $u->student_id)
        ->where('academic_year_id', $year2026->id)
        ->where('fee_head_id', $u->fee_head_id)
        ->where('source_year_id', $year2025->id)
        ->where('is_carry_forward', true)
        ->exists();
    if ($exists) { $skipped++; continue; }

    $srcPayment = \App\Models\FeePayment::where('school_id', $schoolId)
        ->where('student_id', $u->student_id)
        ->where('academic_year_id', $year2025->id)
        ->where('fee_head_id', $u->fee_head_id)
        ->where('is_carry_forward', false)
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
    $created++;
    $totalAmount += (float) $u->outstanding;
}

echo "Created: {$created}\nSkipped: {$skipped}\n";
echo "Total amount carried: " . number_format($totalAmount, 2) . "\n\nDone.\n";
