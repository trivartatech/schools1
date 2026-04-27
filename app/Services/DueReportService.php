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
     * @param  string  $status  'all' | 'defaulter' | 'paid'
     * @return array<int, array<string, mixed>>
     */
    public function rowsFor(
        int $schoolId,
        int $academicYearId,
        ?int $classId = null,
        ?int $sectionId = null,
        string $status = 'all'
    ): array {
        $students = Student::where('school_id', $schoolId)
            ->whereHas('currentAcademicHistory', fn($q) => $q->where('academic_year_id', $academicYearId))
            ->with([
                'currentAcademicHistory.courseClass',
                'currentAcademicHistory.section',
                'studentParent',
                'transportAllocation',
            ])
            ->select('id', 'first_name', 'last_name', 'admission_no', 'gender', 'parent_id')
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

            $totalBalance = $feeDue + $transportDue;

            if ($status === 'defaulter' && $totalBalance <= 0) continue;
            if ($status === 'paid'      && $totalBalance >  0) continue;

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
                'total_balance'  => $totalBalance,
            ];
        }

        return $rows;
    }
}
