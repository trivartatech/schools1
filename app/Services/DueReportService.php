<?php

namespace App\Services;

use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentAcademicHistory;

/**
 * Builds the row set for the Due Report page and its export endpoint.
 * Both LedgerController::dueReport and DueReportExportController call rowsFor()
 * so the on-screen list and the exported file always agree.
 */
class DueReportService
{
    /**
     * Status filter values mirror the manual flag on `students.is_defaulter`,
     * which the user toggles from the student profile page (the "Defaulter /
     * Not Defaulter" pill). It is intentionally NOT derived from total_balance.
     *
     * @param  string        $status     'all' | 'defaulter' | 'not_defaulter'
     * @param  array<string> $feeTypes   subset of ['regular','transport','hostel'].
     *                                   When non-empty, only return rows whose
     *                                   balance is > 0 in at least one of the
     *                                   selected fee types. Empty = no fee-type
     *                                   filter (show everyone).
     * @return array<int, array<string, mixed>>
     */
    public function rowsFor(
        int $schoolId,
        int $academicYearId,
        ?int $classId = null,
        ?int $sectionId = null,
        string $status = 'all',
        array $feeTypes = []
    ): array {
        $students = Student::where('school_id', $schoolId)
            ->whereHas('currentAcademicHistory', fn($q) => $q->where('academic_year_id', $academicYearId))
            ->with([
                'currentAcademicHistory.courseClass',
                'currentAcademicHistory.section',
                'studentParent',
                'transportAllocation',
                'hostelAllocation',
            ])
            ->select('id', 'first_name', 'last_name', 'admission_no', 'gender', 'parent_id', 'is_defaulter')
            ->when($status === 'defaulter',     fn($q) => $q->where('is_defaulter', true))
            ->when($status === 'not_defaulter', fn($q) => $q->where('is_defaulter', false))
            ->when($classId, fn($q) => $q->whereHas('currentAcademicHistory', function ($q2) use ($classId, $sectionId, $academicYearId) {
                $q2->where('class_id', $classId)->where('academic_year_id', $academicYearId);
                if ($sectionId) $q2->where('section_id', $sectionId);
            }))
            ->get();

        $studentIds = $students->pluck('id');

        $feeStructures = FeeStructure::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->get();

        $paymentTotals = FeePayment::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, SUM(amount_paid) as total_paid, SUM(discount) as total_discount')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $historyCounts = StudentAcademicHistory::whereIn('student_id', $studentIds)
            ->selectRaw('student_id, count(*) as count')
            ->groupBy('student_id')
            ->pluck('count', 'student_id');

        // Normalize the fee-type filter
        $allowed   = ['regular', 'transport', 'hostel'];
        $feeTypes  = array_values(array_intersect($allowed, $feeTypes));
        $hasFilter = count($feeTypes) > 0;

        $rows = [];

        foreach ($students as $student) {
            $history = $student->currentAcademicHistory;
            if (!$history) continue;

            $studentType = ($historyCounts[$student->id] ?? 1) > 1 ? 'old' : 'new';
            $gender      = strtolower((string) $student->gender);

            $applicable = $feeStructures->filter(function ($s) use ($history, $studentType, $gender) {
                if ($s->class_id != $history->class_id) return false;
                if ($s->student_type !== 'all' && $s->student_type !== $studentType) return false;
                if ($s->gender !== 'all' && strtolower((string) $s->gender) !== $gender) return false;
                return true;
            });

            $totalDue      = (float) $applicable->sum('amount');
            $pt            = $paymentTotals->get($student->id);
            $totalPaid     = (float) ($pt->total_paid ?? 0);
            $totalDiscount = (float) ($pt->total_discount ?? 0);
            $feeDue        = max(0.0, $totalDue - $totalPaid - $totalDiscount);

            $alloc         = $student->transportAllocation;
            $transportFee  = (float) ($alloc->transport_fee ?? 0);
            $transportPaid = (float) ($alloc->amount_paid ?? 0);
            $transportDue  = (float) ($alloc->balance ?? 0);

            $hostel        = $student->hostelAllocation;
            $hostelFee     = (float) ($hostel->hostel_fee ?? 0);
            $hostelPaid    = (float) ($hostel->amount_paid ?? 0);
            $hostelDue     = (float) ($hostel->balance ?? 0);

            $totalBalance = $feeDue + $transportDue + $hostelDue;

            // Fee-type filter: keep the row only if at least one of the
            // selected categories has an outstanding balance.
            if ($hasFilter) {
                $matches = false;
                if (in_array('regular',   $feeTypes, true) && $feeDue       > 0) $matches = true;
                if (in_array('transport', $feeTypes, true) && $transportDue > 0) $matches = true;
                if (in_array('hostel',    $feeTypes, true) && $hostelDue    > 0) $matches = true;
                if (! $matches) continue;
            }

            $className = trim(
                ($history->courseClass?->name ?? '') . ' - ' . ($history->section?->name ?? ''),
                ' -'
            );

            $rows[] = [
                'student_id'     => $student->id,
                'name'           => trim($student->first_name . ' ' . $student->last_name),
                'class'          => $className,
                'father_contact' => $student->studentParent?->father_phone ?: '-',
                'mother_contact' => $student->studentParent?->mother_phone ?: '-',
                'total_fee'      => $totalDue,
                'paid_fee'       => $totalPaid,
                'fee_due'        => $feeDue,
                'transport_fee'  => $transportFee,
                'transport_paid' => $transportPaid,
                'transport_due'  => $transportDue,
                'hostel_fee'     => $hostelFee,
                'hostel_paid'    => $hostelPaid,
                'hostel_due'     => $hostelDue,
                'total_balance'  => $totalBalance,
                'is_defaulter'   => (bool) $student->is_defaulter,
            ];
        }

        return $rows;
    }
}
