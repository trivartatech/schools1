<?php

namespace App\Services;

use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\TransportStudentAllocation;

/**
 * TransportFeeService
 *
 * Bridges the Transport module with the Finance / Fee module.
 *
 * When a student is allocated to a transport route:
 *  1. A dedicated "Transport Fee" FeeHead is found (or auto-created) for the school.
 *  2. A FeePayment record with status = 'due' and amount_due = stop.fee is created.
 *  3. The FeePayment ID is stored in transport_student_allocation.fee_payment_id
 *     so the two records stay linked.
 *
 * On update: if the fee amount changed, the existing FeePayment is updated.
 * On destroy: the linked FeePayment is soft-deleted (not physically removed).
 */
class TransportFeeService
{
    /**
     * Create a FeePayment when a new allocation is saved.
     */
    public function createFeeEntry(TransportStudentAllocation $allocation): ?FeePayment
    {
        if (!$allocation->transport_fee || $allocation->transport_fee <= 0) {
            return null;
        }

        $feeHead = $this->resolveTransportFeeHead($allocation->school_id);

        $academicYearId = $this->resolveAcademicYearId($allocation->school_id);

        $payment = FeePayment::create([
            'school_id'       => $allocation->school_id,
            'student_id'      => $allocation->student_id,
            'academic_year_id'=> $academicYearId,
            'fee_head_id'     => $feeHead->id,
            'amount_due'      => $allocation->transport_fee,
            'amount_paid'     => 0,
            'discount'        => 0,
            'fine'            => 0,
            'balance'         => $allocation->transport_fee,
            'term'            => 'annual',
            'payment_date'    => now()->toDateString(),
            'payment_mode'    => 'cash',
            'status'          => 'unpaid',
            'remarks'         => 'Auto-created by Transport Allocation. Route: ' .
                                  optional($allocation->route)->route_code .
                                  ' | Stop: ' . optional($allocation->stop)->stop_name,
            'collected_by'    => auth()->id(),
        ]);

        // Link back
        $allocation->update(['fee_payment_id' => $payment->id]);

        return $payment;
    }

    /**
     * Update the linked FeePayment if fee or status changed.
     */
    public function syncFeeEntry(TransportStudentAllocation $allocation): void
    {
        if (!$allocation->fee_payment_id) {
            // No link yet — create one
            $this->createFeeEntry($allocation);
            return;
        }

        $payment = FeePayment::find($allocation->fee_payment_id);
        if (!$payment) {
            $this->createFeeEntry($allocation);
            return;
        }

        // Only update if still unpaid / due
        if (in_array($payment->status->value, ['unpaid', 'partial'])) {
            $newFee = $allocation->transport_fee ?? 0;
            $paid   = $payment->amount_paid;
            $balance= max(0, $newFee - $paid);

            $payment->update([
                'amount_due' => $newFee,
                'balance'    => $balance,
                'status'     => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            ]);
        }

        // If allocation is deactivated, mark fee as waived
        if ($allocation->status === 'inactive' && $payment->status->value === 'unpaid') {
            $payment->update(['status' => 'waived', 'remarks' => $payment->remarks . ' | Allocation deactivated.']);
        }
    }

    /**
     * Waive/cancel the linked FeePayment when an allocation is deleted.
     */
    public function cancelFeeEntry(TransportStudentAllocation $allocation): void
    {
        if (!$allocation->fee_payment_id) return;

        $payment = FeePayment::find($allocation->fee_payment_id);
        if ($payment && in_array($payment->status->value, ['unpaid', 'partial'])) {
            $payment->update([
                'status'  => 'waived',
                'remarks' => ($payment->remarks ?? '') . ' | Transport allocation removed.',
            ]);
        }
    }

    /**
     * Find the transport FeeHead for this school.
     *
     * Priority:
     *  1. Any FeeHead with is_transport_fee = true (admin-configured via the toggle)
     *  2. Legacy fallback: short_code = 'TRANSPORT' (auto-created in earlier versions)
     *  3. Create a new one with is_transport_fee = true if neither exists
     */
    private function resolveTransportFeeHead(int $schoolId): FeeHead
    {
        // Priority 1: admin explicitly marked a head as transport head
        $head = FeeHead::where('school_id', $schoolId)
            ->where('is_transport_fee', true)
            ->first();

        if ($head) return $head;

        // Priority 2: legacy auto-created head by short_code
        $head = FeeHead::where('school_id', $schoolId)
            ->where('short_code', 'TRANSPORT')
            ->first();

        if ($head) {
            // Mark it retroactively so future lookups hit priority 1
            $head->update(['is_transport_fee' => true]);
            return $head;
        }

        // Priority 3: create a new head
        return FeeHead::create([
            'school_id'         => $schoolId,
            'name'              => 'Transport Fee',
            'short_code'        => 'TRANSPORT',
            'is_taxable'        => false,
            'is_transport_fee'  => true,
            'gst_percent'       => 0,
            'sort_order'        => 99,
        ]);
    }


    /**
     * Resolve the current academic year for the school.
     */
    private function resolveAcademicYearId(int $schoolId): int
    {
        // Honor the session-resolved binding from ResolveTenant middleware
        if (app()->bound('current_academic_year_id')) {
            return app('current_academic_year_id');
        }

        // Fallback: query directly
        $year = \App\Models\AcademicYear::where('school_id', $schoolId)
            ->where('is_current', true)
            ->first();

        return $year?->id ?? \App\Models\AcademicYear::where('school_id', $schoolId)
            ->orderBy('start_date', 'desc')
            ->value('id');
    }
}
