<?php

namespace App\Services;

use App\Models\Student;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeHead;
use App\Models\TransportStudentAllocation;
use App\Models\StudentAcademicHistory;
use Illuminate\Support\Collection;

class FeeService
{
    /**
     * Get a comprehensive fee summary for a student in a specific academic year.
     *
     * All assigned fee heads are always visible with clear paid/unpaid/partial
     * status tracking. Balance is calculated from fee structures (what's assigned)
     * minus actual payments recorded in the Fee module.
     *
     * Pass pre-loaded collections via $preloaded to avoid re-querying when
     * the caller (e.g. DashboardController) has already eager-loaded the data:
     *
     *   $preloaded = [
     *     'payments'             => $student->feePayments,        // Collection<FeePayment>
     *     'transportAllocation'  => $student->transportAllocation, // Model|null
     *     'hostelAllocation'     => $student->hostelAllocation,    // Model|null
     *   ]
     *
     * @param Student $student
     * @param int     $academicYearId
     * @param int|null $schoolId
     * @param array   $preloaded   Optional pre-loaded relations — skips DB queries when provided
     */
    public function getStudentFeeSummary(
        Student $student,
        int $academicYearId,
        ?int $schoolId = null,
        array $preloaded = []
    ): array {
        $schoolId = $schoolId ?? $student->school_id;

        // 1. Academic history
        $history = $student->currentAcademicHistory;
        if (!$history || $history->academic_year_id != $academicYearId) {
             $history = StudentAcademicHistory::where('student_id', $student->id)
                ->where('academic_year_id', $academicYearId)
                ->first();
        }
        if (!$history) return $this->emptySummary();

        $classId     = $history->class_id;
        $gender      = strtolower($student->gender ?? 'all');
        $historyCount= StudentAcademicHistory::where('student_id', $student->id)->count();
        $studentType = $historyCount > 1 ? 'old' : 'new';

        // 2. Fee Structures (always fetched — not typically pre-loadable from Student)
        $structures = FeeStructure::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->whereIn('gender', ['all', $gender])
            ->whereIn('student_type', ['all', $studentType])
            ->with('feeHead')
            ->get();

        // 3. Payments — use pre-loaded collection if available, otherwise query
        $payments = isset($preloaded['payments'])
            ? $preloaded['payments']->filter(fn($p) => $p->academic_year_id == $academicYearId)->values()
            : FeePayment::where('student_id', $student->id)
                ->where('academic_year_id', $academicYearId)
                ->with('feeHead')
                ->get();

        // 4a. Transport Fee — use pre-loaded allocation if provided
        $transportFee        = 0;
        $transportAllocation = $preloaded['transportAllocation']
            ?? TransportStudentAllocation::where('student_id', $student->id)
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->first();
        if ($transportAllocation) {
            $transportFee = (float) $transportAllocation->transport_fee;
        }

        // 4b. Hostel Fee — use pre-loaded allocation if provided
        $hostelFee        = 0;
        $hostelAllocation = $preloaded['hostelAllocation']
            ?? \App\Models\HostelStudent::with('bed.room')
                ->where('student_id', $student->id)
                ->whereNull('vacate_date')
                ->whereRaw('LOWER(status) = ?', ['active'])
                ->first();
        if ($hostelAllocation && $hostelAllocation->bed && $hostelAllocation->bed->room) {
            $hostelFee = (float) $hostelAllocation->bed->room->cost_per_month;
        }

        // C. Class fees total (excluding transport + hostel heads)
        $classTotal = $structures->filter(
            fn($s) => !($s->feeHead?->is_transport_fee ?? false) && !($s->feeHead?->is_hostel_fee ?? false)
        )->sum('amount');

        // D. Ad-hoc fees (payments not in class structures)
        $structureHeadIds = $structures->pluck('fee_head_id')->unique();
        $adhocFeesTotal = $payments->filter(function($p) use ($structureHeadIds) {
            if ($p->feeHead?->is_transport_fee ?? false) return false;
            if ($p->feeHead?->is_hostel_fee ?? false) return false;
            return !in_array($p->fee_head_id, $structureHeadIds->toArray()) &&
                   in_array($p->status, ['due', 'partial', 'paid']);
        })->sum('amount_due');

        $totalDue = $classTotal + $transportFee + $hostelFee + $adhocFeesTotal;

        // D. Payments/Discounts/Fines (Real Money)
        $totalPaid = $payments->sum('amount_paid');
        $totalDiscount = $payments->sum('discount');
        $totalFine = $payments->sum('fine');

        // Balance Calculation matching logic in FeeController
        // Balance = TotalDue - (Paid + Discount) + Fines
        $balance = max(0, $totalDue - ($totalPaid + $totalDiscount) + $totalFine);

        // 5. Build Head-wise Breakdown — ALL fee heads visible with status
        $feeHeadsArr = [];

        // A. Class Fees — every structure entry is always shown
        foreach ($structures as $s) {
            if ($s->feeHead?->is_transport_fee || $s->feeHead?->is_hostel_fee) continue;

            $p = $payments->where('fee_head_id', $s->fee_head_id)->where('term', $s->term);
            $pPaid = $p->sum('amount_paid');
            $pDiscount = $p->sum('discount');
            $pFine = $p->sum('fine');
            $pBal = max(0, $s->amount - ($pPaid + $pDiscount) + $pFine);

            // Determine payment status
            if ($pBal <= 0) {
                $status = 'paid';
            } elseif ($pPaid > 0 || $pDiscount > 0) {
                $status = 'partial';
            } else {
                $status = 'unpaid';
            }

            $feeHeadsArr[] = [
                'fee_head_id' => $s->fee_head_id,
                'head_name'   => $s->feeHead?->name ?? 'Class Fee',
                'term'        => $s->term,
                'amount_due'  => $s->amount,
                'amount_paid' => $pPaid,
                'discount'    => $pDiscount,
                'balance'     => $pBal,
                'status'      => $status,
            ];
        }

        // B. Transport Fee
        if ($transportFee > 0) {
            $transportHead = \App\Models\FeeHead::where('school_id', $schoolId)->where('is_transport_fee', true)->first();
            $tPayments = $transportHead ? $payments->where('fee_head_id', $transportHead->id) : collect();

            $tPaid = $tPayments->sum('amount_paid');
            $tDiscount = $tPayments->sum('discount');
            $tFine = $tPayments->sum('fine');
            $tBal = max(0, $transportFee - ($tPaid + $tDiscount) + $tFine);

            if ($tBal <= 0) {
                $tStatus = 'paid';
            } elseif ($tPaid > 0 || $tDiscount > 0) {
                $tStatus = 'partial';
            } else {
                $tStatus = 'unpaid';
            }

            $feeHeadsArr[] = [
                'fee_head_id' => $transportHead->id ?? null,
                'head_name'   => 'Transport Fee',
                'term'        => 'Annual',
                'amount_due'  => $transportFee,
                'amount_paid' => $tPaid,
                'discount'    => $tDiscount,
                'balance'     => $tBal,
                'status'      => $tStatus,
            ];
        }

        // C. Hostel Fee
        if ($hostelFee > 0) {
            $hHead = \App\Models\FeeHead::where('school_id', $schoolId)->where('is_hostel_fee', true)->first();
            $hPayments = $hHead ? $payments->where('fee_head_id', $hHead->id) : collect();

            $hPaid = $hPayments->sum('amount_paid');
            $hDiscount = $hPayments->sum('discount');
            $hFine = $hPayments->sum('fine');
            $hBal = max(0, $hostelFee - ($hPaid + $hDiscount) + $hFine);

            if ($hBal <= 0) {
                $hStatus = 'paid';
            } elseif ($hPaid > 0 || $hDiscount > 0) {
                $hStatus = 'partial';
            } else {
                $hStatus = 'unpaid';
            }

            $feeHeadsArr[] = [
                'fee_head_id' => $hHead->id ?? null,
                'head_name'   => 'Hostel Fee',
                'term'        => 'Annual',
                'amount_due'  => $hostelFee,
                'amount_paid' => $hPaid,
                'discount'    => $hDiscount,
                'balance'     => $hBal,
                'status'      => $hStatus,
            ];
        }

        // D. Adhoc Fees (Arrears, special charges, existing but not in structure)
        $adhocList = $payments->filter(function($p) use ($structureHeadIds) {
            if ($p->feeHead?->is_transport_fee ?? false) return false;
            if ($p->feeHead?->is_hostel_fee ?? false) return false;
            return !in_array($p->fee_head_id, $structureHeadIds->toArray()) &&
                   in_array($p->status, ['due', 'partial', 'paid']);
        });

        foreach ($adhocList as $adhoc) {
            $aBal = (float) $adhoc->balance;

            if ($aBal <= 0) {
                $aStatus = 'paid';
            } elseif ((float) $adhoc->amount_paid > 0 || (float) $adhoc->discount > 0) {
                $aStatus = 'partial';
            } else {
                $aStatus = 'unpaid';
            }

            $feeHeadsArr[] = [
                'fee_head_id' => $adhoc->fee_head_id,
                'head_name'   => $adhoc->feeHead->name ?? 'Ad-Hoc Fee',
                'term'        => $adhoc->term,
                'amount_due'  => $adhoc->amount_due,
                'amount_paid' => $adhoc->amount_paid,
                'discount'    => $adhoc->discount,
                'balance'     => $aBal,
                'status'      => $aStatus,
            ];
        }

        return [
            'total_due'      => $totalDue,
            'paid'           => $totalPaid,
            'discount'       => $totalDiscount,
            'fine'           => $totalFine,
            'balance'        => $balance,
            'class_fees'     => $classTotal,
            'transport_fee'  => $transportFee,
            'hostel_fee'     => $hostelFee,
            'adhoc_fees'     => $adhocFeesTotal,
            'fee_heads'      => $feeHeadsArr,
        ];
    }

    /**
     * Get school-wide pending fees: total outstanding and top students with balance.
     *
     * Calculates total due from fee structures (+ transport + hostel allocations)
     * minus total payments, so fee heads without payment records are still counted.
     */
    public function getSchoolPendingFees(int $schoolId, ?int $academicYearId): array
    {
        if (!$academicYearId) {
            return ['pending_fees' => 0, 'pending_fee_students' => []];
        }

        // 1. All active students with their class for this year
        $studentHistories = StudentAcademicHistory::where('academic_year_id', $academicYearId)
            ->whereHas('student', fn($q) => $q->where('school_id', $schoolId)->where('status', 'active'))
            ->with('student:id,first_name,last_name,gender,school_id,photo_url')
            ->get();

        if ($studentHistories->isEmpty()) {
            return ['pending_fees' => 0, 'pending_fee_students' => []];
        }

        // 2. All fee structures for this academic year (one query, reused for all students)
        $structures = FeeStructure::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->with('feeHead')
            ->get();

        // 3. History counts for new/old student determination
        $studentIds = $studentHistories->pluck('student_id')->all();
        $historyCounts = StudentAcademicHistory::whereIn('student_id', $studentIds)
            ->selectRaw('student_id, COUNT(*) as cnt')
            ->groupBy('student_id')
            ->pluck('cnt', 'student_id');

        // 4. Calculate per-student structure total (class fees only, exclude transport/hostel heads)
        $studentDues = [];
        foreach ($studentHistories as $sh) {
            $classId     = $sh->class_id;
            $gender      = strtolower($sh->student->gender ?? 'all');
            $studentType = ($historyCounts[$sh->student_id] ?? 1) > 1 ? 'old' : 'new';

            $studentTotal = $structures->filter(function ($s) use ($classId, $gender, $studentType) {
                if ($s->class_id != $classId) return false;
                if (!in_array($s->gender, ['all', $gender])) return false;
                if (!in_array($s->student_type, ['all', $studentType])) return false;
                if ($s->feeHead?->is_transport_fee || $s->feeHead?->is_hostel_fee) return false;
                return true;
            })->sum('amount');

            $studentDues[$sh->student_id] = (float) $studentTotal;
        }

        // 5. Add transport allocation fees
        $transportAllocations = TransportStudentAllocation::where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereIn('student_id', $studentIds)
            ->get(['student_id', 'transport_fee']);

        foreach ($transportAllocations as $ta) {
            $studentDues[$ta->student_id] = ($studentDues[$ta->student_id] ?? 0) + (float) $ta->transport_fee;
        }

        // 6. Add hostel allocation fees
        $hostelAllocations = \App\Models\HostelStudent::with('bed.room')
            ->whereIn('student_id', $studentIds)
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->whereNull('vacate_date')
            ->get();

        foreach ($hostelAllocations as $ha) {
            $hFee = (float) ($ha->bed?->room?->cost_per_month ?? 0);
            $studentDues[$ha->student_id] = ($studentDues[$ha->student_id] ?? 0) + $hFee;
        }

        // 7. Add adhoc fees (payments for fee heads not in any structure)
        $structureHeadTermKeys = $structures->map(fn($s) => $s->fee_head_id . '|' . $s->term)->unique()->all();

        $adhocPayments = \App\Models\FeePayment::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['due', 'partial', 'paid'])
            ->with('feeHead')
            ->get()
            ->filter(function ($p) use ($structureHeadTermKeys) {
                if ($p->feeHead?->is_transport_fee ?? false) return false;
                if ($p->feeHead?->is_hostel_fee ?? false) return false;
                $key = $p->fee_head_id . '|' . $p->term;
                return !in_array($key, $structureHeadTermKeys);
            });

        foreach ($adhocPayments as $ap) {
            $studentDues[$ap->student_id] = ($studentDues[$ap->student_id] ?? 0) + (float) $ap->amount_due;
        }

        // 8. Total paid + discount per student
        $paymentsByStudent = \App\Models\FeePayment::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, SUM(amount_paid) as total_paid, SUM(discount) as total_discount, SUM(fine) as total_fine')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        // 9. Calculate outstanding per student
        $studentBalances = [];
        foreach ($studentDues as $studentId => $due) {
            $sp   = $paymentsByStudent[$studentId] ?? null;
            $paid = $sp ? ((float) $sp->total_paid + (float) $sp->total_discount) : 0;
            $fine = $sp ? (float) $sp->total_fine : 0;
            $bal  = max(0, $due - $paid + $fine);
            if ($bal > 0) {
                $studentBalances[$studentId] = $bal;
            }
        }

        $pendingFees = array_sum($studentBalances);

        // 10. Top 5 students with highest outstanding
        arsort($studentBalances);
        $topIds    = array_slice(array_keys($studentBalances), 0, 5, true);
        $studentMap = $studentHistories->keyBy('student_id');

        $pendingFeeStudents = collect($topIds)->map(function ($id) use ($studentBalances, $studentMap) {
            $s = $studentMap[$id]->student ?? null;
            return [
                'student' => $s ? ($s->first_name . ' ' . $s->last_name) : '—',
                'balance' => $studentBalances[$id],
            ];
        })->values();

        return [
            'pending_fees'         => $pendingFees,
            'pending_fee_students' => $pendingFeeStudents,
        ];
    }

    private function emptySummary(): array
    {
        return [
            'total_due'     => 0,
            'paid'          => 0,
            'discount'      => 0,
            'fine'          => 0,
            'balance'       => 0,
            'class_fees'    => 0,
            'transport_fee' => 0,
            'hostel_fee'    => 0,
            'adhoc_fees'    => 0,
            'fee_heads'     => [],
        ];
    }
}
