<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\HostelBed;
use App\Models\HostelStudent;
use Carbon\Carbon;

/**
 * HostelFeeService
 *
 * Single-total model: each HostelStudent allocation carries its own
 * running balance (`hostel_fee` total + `amount_paid`/`balance` columns).
 * Receipts live in `hostel_fee_payments`, not in Finance's `fee_payments`.
 *
 * On allocation: seedAllocationFee() locks in
 *   hostel_fee = bed.room.cost_per_month × months_opted
 * where months_opted defaults to the months between admission_date and
 * the active academic year end.
 *
 * On vacate: caller updates allocation.status = 'Vacated' and calls
 * recalculateTotals() on the model — payment_status flips to 'waived'
 * if no payments were collected.
 */
class HostelFeeService
{
    /**
     * Seed payment columns on a freshly created HostelStudent allocation.
     */
    public function seedAllocationFee(HostelStudent $allocation, ?float $monthsOpted = null): void
    {
        $bed = HostelBed::with('room')->find($allocation->hostel_bed_id);
        $monthlyFee = (float) ($bed?->room?->cost_per_month ?? 0);

        if ($monthsOpted === null) {
            $monthsOpted = $this->resolveDefaultMonths($allocation->school_id, $allocation->admission_date);
        }
        $monthsOpted = max(0, (float) $monthsOpted);

        $hostelFee = round($monthlyFee * $monthsOpted, 2);

        $allocation->update([
            'hostel_fee'     => $hostelFee,
            'months_opted'   => $monthsOpted,
            'amount_paid'    => 0,
            'discount'       => 0,
            'fine'           => 0,
            'balance'        => $hostelFee,
            'payment_status' => $hostelFee > 0 ? 'unpaid' : 'paid',
        ]);
    }

    /**
     * Default months opted = months from admission_date to the end of the
     * current academic year. Returns 0 if either date is missing.
     */
    private function resolveDefaultMonths(int $schoolId, $admissionDate): float
    {
        if (!$admissionDate) {
            return 0;
        }

        $year = AcademicYear::where('school_id', $schoolId)
            ->where('is_current', true)
            ->first();

        $end = $year?->end_date;
        if (!$end) {
            return 0;
        }

        $start = Carbon::parse($admissionDate);
        $end   = Carbon::parse($end);

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $months = $start->diffInMonths($end) + ($start->diffInDays($end) % 30) / 30;
        return round($months, 2);
    }
}
