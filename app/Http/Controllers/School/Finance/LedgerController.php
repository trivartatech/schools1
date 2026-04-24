<?php

namespace App\Http\Controllers\School\Finance;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\FeePayment;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class LedgerController extends Controller
{
    /**
     * Day Book: Shows daily inflows (Fees) and outflows (Expenses)
     */
    public function dayBook(Request $request)
    {
        $schoolId = app('current_school_id');
        
        $academicYearId = app('current_academic_year_id');
        $academicYear = \App\Models\AcademicYear::find($academicYearId);

        $startDate = $request->input('start_date', $request->input('date', date('Y-m-d')));
        $endDate = $request->input('end_date', $request->input('date', date('Y-m-d')));

        // Inflows (Fee Collections)
        // Use whereDate instead of whereBetween — SQLite stores dates with time component
        // so whereBetween('payment_date', ['2026-04-02', '2026-04-02']) misses '2026-04-02 00:00:00'
        $feeQuery = FeePayment::where('school_id', $schoolId)
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->where('amount_paid', '>', 0)
            ->with(['student.currentAcademicHistory.courseClass', 'student.currentAcademicHistory.section', 'feeHead']);

        if ($request->filled('class_id')) {
            $feeQuery->whereHas('student.currentAcademicHistory', function($q) use ($request, $academicYearId) {
                $q->where('class_id', $request->class_id);
                if ($request->filled('section_id')) {
                    $q->where('section_id', $request->section_id);
                }
                $q->where('academic_year_id', $academicYearId);
            });
        }

        $feePayments = $feeQuery->get();

        // Outflows (Expenses)
        $expenses = Expense::where('school_id', $schoolId)
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate)
            ->with(['category'])
            ->get();

        $totalInflow = $feePayments->sum('amount_paid');
        $totalOutflow = $expenses->sum('amount');
        $netBalance = $totalInflow - $totalOutflow;
        
        $classes = \App\Models\CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get();

        return Inertia::render('School/Finance/Ledger/DayBook', [
            'feePayments' => $feePayments,
            'expenses' => $expenses,
            'classes' => $classes,
            'summary' => [
                'total_inflow' => $totalInflow,
                'total_outflow' => $totalOutflow,
                'net_balance' => $netBalance,
            ],
            'filters' => [
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'class_id'   => $request->class_id,
                'section_id' => $request->section_id,
            ]
        ]);
    }

    /**
     * Due Report / Defaulter List
     * Scans all active students, calculates their Total Due based on fee structures,
     * subtracts their total paid, and shows the balance.
     */
    public function dueReport(Request $request)
    {
        $schoolId = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        // We fetch students with their fee payments and active class history
        // Contact number is sourced from the parent row (student_parents.primary_phone);
        // `contact_no` is NOT a column on students.
        $query = Student::where('school_id', $schoolId)
            ->whereHas('currentAcademicHistory', function ($q) use ($academicYearId) {
                $q->where('academic_year_id', $academicYearId);
            })
            ->with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section', 'studentParent'])
            ->select('id', 'first_name', 'last_name', 'admission_no', 'gender', 'parent_id');

        if ($request->filled('class_id')) {
            $query->whereHas('currentAcademicHistory', function($q) use ($request, $academicYearId) {
                $q->where('class_id', $request->class_id);
                if ($request->filled('section_id')) {
                    $q->where('section_id', $request->section_id);
                }
                $q->where('academic_year_id', $academicYearId);
            });
        }

        $students = $query->get();
        $studentIds = $students->pluck('id');

        // Load fee structures (small set — no N+1 risk)
        $feeStructures = \App\Models\FeeStructure::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->get();

        // Performance fix: single SQL aggregation instead of loading all payment rows
        $paymentTotals = FeePayment::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, SUM(amount_paid) as total_paid, SUM(discount) as total_discount')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        // History counts for student type (new/old) determination
        $historyCounts = \App\Models\StudentAcademicHistory::whereIn('student_id', $studentIds)
            ->selectRaw('student_id, count(*) as count')
            ->groupBy('student_id')
            ->pluck('count', 'student_id');

        $defaulters = [];

        foreach ($students as $student) {
            $history = $student->currentAcademicHistory;
            if (!$history) continue;

            $classId = $history->class_id;
            $historyCount = $historyCounts[$student->id] ?? 1;
            $studentType = $historyCount > 1 ? 'old' : 'new';
            $gender = strtolower($student->gender);

            // Filter applicable structures (in-memory, structures are small)
            $applicableStructures = $feeStructures->filter(function ($structure) use ($classId, $studentType, $gender) {
                if ($structure->class_id != $classId) return false;
                if ($structure->student_type !== 'all' && $structure->student_type !== $studentType) return false;
                if ($structure->gender !== 'all' && strtolower($structure->gender) !== $gender) return false;
                return true;
            });

            $totalDue = $applicableStructures->sum('amount');

            // Aggregated totals — no per-student queries
            $pt            = $paymentTotals->get($student->id);
            $totalPaid     = (float) ($pt->total_paid     ?? 0);
            $totalDiscount = (float) ($pt->total_discount ?? 0);

            $balance = max(0, $totalDue - $totalPaid - $totalDiscount);

            if ($balance > 0) {

                $contactNo = $student->studentParent?->primary_phone
                    ?? $student->studentParent?->father_phone
                    ?? '-';

                $defaulters[] = [
                    'student_id'   => $student->id,
                    'admission_no' => $student->admission_no,
                    'name'         => $student->first_name . ' ' . $student->last_name,
                    'class'        => $history->courseClass?->name . ' - ' . $history->section?->name,
                    'contact_no'   => $contactNo,
                    'total_due'    => $totalDue,
                    'total_paid'   => $totalPaid,
                    'balance_due'  => $balance,
                ];
            }
        }

        // Sort defaulters by balance (highest first)
        usort($defaulters, fn($a, $b) => $b['balance_due'] <=> $a['balance_due']);

        // Fetch classes for the dropdown filter
        $classes = \App\Models\CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get();

        return Inertia::render('School/Finance/Ledger/DueReport', [
            'defaulters' => collect($defaulters)->values(),
            'classes' => $classes,
            'filters' => $request->only('class_id', 'section_id')
        ]);
    }

    /**
     * Fee Summary Report
     * Shows S.No, Admission No, Student, Father, Class, Total Due, Concession, Payable, Paid, Balance.
     */
    public function feeSummaryReport(Request $request)
    {
        $schoolId = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $query = Student::where('school_id', $schoolId)
            ->whereHas('currentAcademicHistory', function ($q) use ($academicYearId) {
                $q->where('academic_year_id', $academicYearId);
            })
            ->with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section', 'studentParent'])
            ->select('id', 'first_name', 'last_name', 'admission_no', 'gender', 'parent_id');

        if ($request->filled('class_id')) {
            $query->whereHas('currentAcademicHistory', function($q) use ($request, $academicYearId) {
                $q->where('class_id', $request->class_id);
                $q->where('academic_year_id', $academicYearId);
            });
        }

        $students = $query->get();
        $studentIds = $students->pluck('id');

        $feeStructures = \App\Models\FeeStructure::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->get();

        // Performance fix: single SQL aggregation instead of loading all payment rows
        $paymentTotals = FeePayment::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, SUM(amount_paid) as total_paid, SUM(discount) as total_discount')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $historyCounts = \App\Models\StudentAcademicHistory::whereIn('student_id', $studentIds)
            ->selectRaw('student_id, count(*) as count')
            ->groupBy('student_id')
            ->pluck('count', 'student_id');

        $reports = [];

        foreach ($students as $student) {
            $history = $student->currentAcademicHistory;
            if (!$history) continue;

            $classId = $history->class_id;
            $historyCount = $historyCounts[$student->id] ?? 1;
            $studentType = $historyCount > 1 ? 'old' : 'new';
            $gender = strtolower($student->gender);

            $applicableStructures = $feeStructures->filter(function ($structure) use ($classId, $studentType, $gender) {
                if ($structure->class_id != $classId) return false;
                if ($structure->student_type !== 'all' && $structure->student_type !== $studentType) return false;
                if ($structure->gender !== 'all' && strtolower($structure->gender) !== $gender) return false;
                return true;
            });

            $totalFeeAmount = $applicableStructures->sum('amount');

            // Aggregated totals — no per-student queries
            $pt              = $paymentTotals->get($student->id);
            $totalPaid       = (float) ($pt->total_paid       ?? 0);
            $totalConcession = (float) ($pt->total_discount   ?? 0);
            $netPayable = max(0, $totalFeeAmount - $totalConcession);
            $balance = max(0, $netPayable - $totalPaid);

            $contactNo = $student->studentParent?->primary_phone
                ?? $student->studentParent?->father_phone
                ?? '-';

            $reports[] = [
                'student_id'   => $student->id,
                'admission_no' => $student->admission_no,
                'name'         => $student->first_name . ' ' . $student->last_name,
                'father_name'  => $student->studentParent?->father_name ?? '-',
                'contact_no'   => $contactNo ?: '-',
                'class'        => $history->courseClass?->name . ' - ' . $history->section?->name,
                'total_fee'    => $totalFeeAmount,
                'concession'   => $totalConcession,
                'payable'      => $netPayable,
                'paid'         => $totalPaid,
                'balance'      => $balance,
            ];
        }

        // Fetch classes for the dropdown filter
        $classes = \App\Models\CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get();

        return Inertia::render('School/Finance/Ledger/FeeSummaryReport', [
            'reports' => collect($reports)->values(),
            'classes' => $classes,
            'filters' => $request->only('class_id', 'section_id')
        ]);
    }
}
