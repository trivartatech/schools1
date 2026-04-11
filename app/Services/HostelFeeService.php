<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\HostelBed;
use App\Models\HostelStudent;

/**
 * HostelFeeService
 *
 * Bridges the Hostel module with the Finance / Fee module.
 *
 * When a student is allocated to a hostel bed:
 *  1. A dedicated "Hostel Fee" FeeHead is found (or auto-created) for the school.
 *  2. A FeePayment record with status = 'due' and amount_due = room.cost_per_month is created.
 *  3. The FeePayment ID is stored in hostel_students.fee_payment_id so records stay linked.
 *
 * On vacate: the linked FeePayment is waived/cancelled (not deleted).
 */
class HostelFeeService
{
    /**
     * Create a FeePayment when a student is allocated to a hostel bed.
     */
    public function createFeeEntry(HostelStudent $allocation): ?FeePayment
    {
        // Load the room through the bed to get the monthly fee
        $bed = HostelBed::with('room')->find($allocation->hostel_bed_id);
        $monthlyFee = $bed?->room?->cost_per_month ?? 0;

        if (!$monthlyFee || $monthlyFee <= 0) {
            return null;
        }

        $feeHead = $this->resolveHostelFeeHead($allocation->school_id);
        $academicYearId = $this->resolveAcademicYearId($allocation->school_id);

        $hostelName = $bed?->room?->hostel?->name ?? 'Hostel';
        $roomNumber = $bed?->room?->room_number ?? '';
        $bedName    = $bed?->name ?? '';

        $payment = FeePayment::create([
            'school_id'        => $allocation->school_id,
            'student_id'       => $allocation->student_id,
            'academic_year_id' => $academicYearId,
            'fee_head_id'      => $feeHead->id,
            'amount_due'       => $monthlyFee,
            'amount_paid'      => 0,
            'discount'         => 0,
            'fine'             => 0,
            'balance'          => $monthlyFee,
            'term'             => 'monthly',
            'payment_date'     => now()->toDateString(),
            'payment_mode'     => 'cash',
            'status'           => 'due',
            'remarks'          => "Auto-created by Hostel Allocation. {$hostelName} → Rm {$roomNumber} → {$bedName}",
            'collected_by'     => auth()->id(),
        ]);

        // Link back to the allocation
        $allocation->update(['fee_payment_id' => $payment->id]);

        return $payment;
    }

    /**
     * Sync / update when allocation changes (future: room transfer).
     */
    public function syncFeeEntry(HostelStudent $allocation): void
    {
        if (!$allocation->fee_payment_id) {
            $this->createFeeEntry($allocation);
            return;
        }

        $payment = FeePayment::find($allocation->fee_payment_id);
        if (!$payment) {
            $this->createFeeEntry($allocation);
            return;
        }

        // If allocation is vacated, cancel the due fee
        if ($allocation->status === 'Vacated' && $payment->status === 'due') {
            $payment->update([
                'status'  => 'waived',
                'remarks' => ($payment->remarks ?? '') . ' | Student vacated.',
            ]);
        }
    }

    /**
     * Cancel the linked FeePayment when a student is vacated.
     */
    public function cancelFeeEntry(HostelStudent $allocation): void
    {
        if (!$allocation->fee_payment_id) return;

        $payment = FeePayment::find($allocation->fee_payment_id);
        if ($payment && in_array($payment->status, ['due', 'partial'])) {
            $payment->update([
                'status'  => 'waived',
                'remarks' => ($payment->remarks ?? '') . ' | Hostel allocation vacated/removed.',
            ]);
        }
    }

    /**
     * Generate recurring monthly hostel fees for all active allocations.
     * Skips students who already have a 'due' hostel fee for the current month.
     */
    public function generateMonthlyFees(int $schoolId, string $month, string $year): int
    {
        $allocations = HostelStudent::where('school_id', $schoolId)
            ->where('status', 'Active')
            ->with('bed.room.hostel')
            ->get();

        $feeHead        = $this->resolveHostelFeeHead($schoolId);
        $academicYearId = $this->resolveAcademicYearId($schoolId);
        $generated      = 0;
        $monthLabel     = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);

        foreach ($allocations as $allocation) {
            $monthlyFee = $allocation->bed?->room?->cost_per_month ?? 0;
            if ($monthlyFee <= 0) continue;

            // Check if fee already exists for this month
            $exists = FeePayment::where('school_id', $schoolId)
                ->where('student_id', $allocation->student_id)
                ->where('fee_head_id', $feeHead->id)
                ->where('term', 'monthly-' . $monthLabel)
                ->exists();

            if ($exists) continue;

            $hostelName = $allocation->bed?->room?->hostel?->name ?? 'Hostel';
            $roomNumber = $allocation->bed?->room?->room_number ?? '';

            FeePayment::create([
                'school_id'        => $schoolId,
                'student_id'       => $allocation->student_id,
                'academic_year_id' => $academicYearId,
                'fee_head_id'      => $feeHead->id,
                'amount_due'       => $monthlyFee,
                'amount_paid'      => 0,
                'discount'         => 0,
                'fine'             => 0,
                'balance'          => $monthlyFee,
                'term'             => 'monthly-' . $monthLabel,
                'payment_date'     => now()->toDateString(),
                'payment_mode'     => 'cash',
                'status'           => 'due',
                'remarks'          => "Monthly hostel fee for {$monthLabel}. {$hostelName} → Rm {$roomNumber}",
                'collected_by'     => auth()->id(),
            ]);

            $generated++;
        }

        return $generated;
    }

    /**
     * Find or create the Hostel Fee Head for this school.
     *
     * Priority:
     *  1. Any FeeHead with is_hostel_fee = true (admin-configured)
     *  2. Legacy fallback: short_code = 'HOSTEL'
     *  3. Auto-create a new hostel fee head
     */
    private function resolveHostelFeeHead(int $schoolId): FeeHead
    {
        // Priority 1: admin explicitly marked a head as hostel head
        $head = FeeHead::where('school_id', $schoolId)
            ->where('is_hostel_fee', true)
            ->first();

        if ($head) return $head;

        // Priority 2: legacy auto-created head by short_code
        $head = FeeHead::where('school_id', $schoolId)
            ->where('short_code', 'HOSTEL')
            ->first();

        if ($head) {
            $head->update(['is_hostel_fee' => true]);
            return $head;
        }

        // Priority 3: auto-create
        return FeeHead::create([
            'school_id'      => $schoolId,
            'name'           => 'Hostel Fee',
            'short_code'     => 'HOSTEL',
            'is_taxable'     => false,
            'is_hostel_fee'  => true,
            'gst_percent'    => 0,
            'sort_order'     => 98,
        ]);
    }

    /**
     * Resolve the current academic year ID.
     */
    private function resolveAcademicYearId(int $schoolId): int
    {
        if (app()->bound('current_academic_year_id')) {
            return app('current_academic_year_id');
        }

        $year = AcademicYear::where('school_id', $schoolId)
            ->where('is_current', true)
            ->first();

        return $year?->id ?? AcademicYear::where('school_id', $schoolId)
            ->orderBy('start_date', 'desc')
            ->value('id');
    }
}
