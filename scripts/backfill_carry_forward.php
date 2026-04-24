<?php

/**
 * One-off: backfill carry-forward rows for unpaid 2024-25 dues that never made
 * it into the current academic year.
 *
 * Usage (from project root):
 *   php artisan tinker --execute="require base_path('scripts/backfill_carry_forward.php');"
 *
 * Idempotent: re-running will skip any (student, fee_head) pair that already has
 * a carry-forward row from 2024-25 in the target year.
 */

$schoolId     = 1;
$sourceName   = '2024-25';

$sourceYear = \App\Models\AcademicYear::where('school_id', $schoolId)
    ->where('name', $sourceName)
    ->first();

$targetYear = \App\Models\AcademicYear::where('school_id', $schoolId)
    ->where('is_current', 1)
    ->first();

if (!$sourceYear || !$targetYear) {
    echo "ERROR: source or target year not found.\n";
    return;
}

echo "Source Year: {$sourceYear->name} (id={$sourceYear->id})\n";
echo "Target Year: {$targetYear->name} (id={$targetYear->id})\n\n";

$unpaid = \App\Models\FeePayment::where('school_id', $schoolId)
    ->where('academic_year_id', $sourceYear->id)
    ->whereIn('status', ['due', 'partial'])
    ->where('balance', '>', 0)
    ->where('is_carry_forward', false)
    ->selectRaw('student_id, fee_head_id, SUM(balance) as outstanding')
    ->groupBy('student_id', 'fee_head_id')
    ->havingRaw('SUM(balance) > 0')
    ->get();

echo "Unpaid (student, fee_head) groups found in {$sourceYear->name}: " . $unpaid->count() . "\n";

$created = 0;
$skipped = 0;
$totalAmount = 0.0;

foreach ($unpaid as $u) {
    $exists = \App\Models\FeePayment::where('school_id', $schoolId)
        ->where('student_id', $u->student_id)
        ->where('academic_year_id', $targetYear->id)
        ->where('fee_head_id', $u->fee_head_id)
        ->where('source_year_id', $sourceYear->id)
        ->where('is_carry_forward', true)
        ->exists();

    if ($exists) {
        $skipped++;
        continue;
    }

    $srcPayment = \App\Models\FeePayment::where('school_id', $schoolId)
        ->where('student_id', $u->student_id)
        ->where('academic_year_id', $sourceYear->id)
        ->where('fee_head_id', $u->fee_head_id)
        ->whereIn('status', ['due', 'partial'])
        ->orderBy('id')
        ->first();

    \App\Models\FeePayment::create([
        'school_id'         => $schoolId,
        'student_id'        => $u->student_id,
        'academic_year_id'  => $targetYear->id,
        'fee_head_id'       => $u->fee_head_id,
        'amount_due'        => number_format((float) $u->outstanding, 2, '.', ''),
        'amount_paid'       => 0,
        'discount'          => 0,
        'fine'              => 0,
        'term'              => 'annual',
        'payment_date'      => now()->toDateString(),
        'payment_mode'      => 'cash',
        'status'            => 'due',
        'is_carry_forward'  => true,
        'source_payment_id' => $srcPayment?->id,
        'source_year_id'    => $sourceYear->id,
        'remarks'           => "Carried forward from {$sourceYear->name} (backfill)",
    ]);

    $created++;
    $totalAmount += (float) $u->outstanding;
}

echo "Created: {$created}\n";
echo "Skipped (already exists): {$skipped}\n";
echo "Total amount carried: " . number_format($totalAmount, 2) . "\n";
