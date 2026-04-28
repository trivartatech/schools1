<?php

namespace App\Http\Controllers\School\Finance;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\FeePayment;
use App\Models\Student;
use App\Models\TransportFeePayment;
use App\Services\DueReportService;
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

        // Inflows (Transport Fee Collections) — same date filter; class filter
        // applies via the student's current academic history.
        $transportQuery = TransportFeePayment::where('school_id', $schoolId)
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->where('amount_paid', '>', 0)
            ->with(['student.currentAcademicHistory.courseClass', 'student.currentAcademicHistory.section', 'allocation.route', 'allocation.stop']);

        if ($request->filled('class_id')) {
            $transportQuery->whereHas('student.currentAcademicHistory', function ($q) use ($request, $academicYearId) {
                $q->where('class_id', $request->class_id);
                if ($request->filled('section_id')) {
                    $q->where('section_id', $request->section_id);
                }
                $q->where('academic_year_id', $academicYearId);
            });
        }

        $transportPayments = $transportQuery->get();

        // Outflows (Expenses)
        $expenses = Expense::where('school_id', $schoolId)
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate)
            ->with(['category'])
            ->get();

        $totalTuitionInflow   = (float) $feePayments->sum('amount_paid');
        $totalTransportInflow = (float) $transportPayments->sum('amount_paid');
        $totalInflow          = $totalTuitionInflow + $totalTransportInflow;
        $totalOutflow         = (float) $expenses->sum('amount');
        $netBalance           = $totalInflow - $totalOutflow;

        $classes = \App\Models\CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get();

        return Inertia::render('School/Finance/Ledger/DayBook', [
            'feePayments'       => $feePayments,
            'transportPayments' => $transportPayments,
            'expenses'          => $expenses,
            'classes'           => $classes,
            'summary' => [
                'total_inflow'           => $totalInflow,
                'total_tuition_inflow'   => $totalTuitionInflow,
                'total_transport_inflow' => $totalTransportInflow,
                'total_outflow'          => $totalOutflow,
                'net_balance'            => $netBalance,
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
     * Lists every active student of the current academic year with their regular fee
     * (total/paid/due), transport fee (total/paid/due), and combined Total Fee Balance.
     * Search, sort and pagination happen client-side; the server returns the full set
     * filtered only by class/section/status.
     */
    public function dueReport(Request $request, DueReportService $service)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $status = in_array($request->status, ['all', 'defaulter', 'not_defaulter'], true)
            ? $request->status
            : 'all';

        // fee_types comes either as repeated query params (?fee_types[]=regular)
        // or comma-separated (?fee_types=regular,transport). Normalize either way.
        $feeTypesIn = $request->input('fee_types', []);
        if (is_string($feeTypesIn)) {
            $feeTypesIn = array_filter(array_map('trim', explode(',', $feeTypesIn)));
        }
        $feeTypes = array_values(array_intersect(['regular', 'transport', 'hostel'], (array) $feeTypesIn));

        $rows = $service->rowsFor(
            $schoolId,
            $academicYearId,
            $request->filled('class_id')   ? (int) $request->class_id   : null,
            $request->filled('section_id') ? (int) $request->section_id : null,
            $status,
            $feeTypes,
        );

        $classes = \App\Models\CourseClass::where('school_id', $schoolId)
            ->orderBy('numeric_value')
            ->orderBy('name')
            ->get();

        return Inertia::render('School/Finance/Ledger/DueReport', [
            'defaulters' => collect($rows)->values(),
            'classes'    => $classes,
            'filters'    => [
                'class_id'   => $request->class_id,
                'section_id' => $request->section_id,
                'status'     => $status,
                'fee_types'  => $feeTypes,
            ],
        ]);
    }

    /**
     * Send Fee Due Reminder
     * Triggers SMS / WhatsApp / Voice reminders to parents of selected students.
     * Channels actually fired depend on which fee_due_reminder templates the
     * school has configured as active. Recomputes balance server-side so the
     * amount in the message is always current.
     */
    public function sendDueReminder(Request $request)
    {
        $validated = $request->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
            'due_date'      => 'nullable|string|max:50',
        ]);

        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $school         = app('current_school');
        $dueDate        = $validated['due_date'] ?? now()->format('d-M-Y');

        $students = Student::where('school_id', $schoolId)
            ->whereIn('id', $validated['student_ids'])
            ->whereHas('currentAcademicHistory', fn($q) => $q->where('academic_year_id', $academicYearId))
            ->with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section', 'studentParent'])
            ->get();

        $feeStructures = \App\Models\FeeStructure::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->get();

        $studentIds = $students->pluck('id');

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

        $notifier = new \App\Services\NotificationService($school);

        $sent = 0;
        $skippedNoPhone = 0;
        $skippedNoBalance = 0;

        foreach ($students as $student) {
            $history = $student->currentAcademicHistory;
            if (!$history) { $skippedNoBalance++; continue; }

            $studentType = ($historyCounts[$student->id] ?? 1) > 1 ? 'old' : 'new';
            $gender      = strtolower($student->gender);

            $totalDue = $feeStructures->filter(function ($s) use ($history, $studentType, $gender) {
                if ($s->class_id != $history->class_id) return false;
                if ($s->student_type !== 'all' && $s->student_type !== $studentType) return false;
                if ($s->gender !== 'all' && strtolower($s->gender) !== $gender) return false;
                return true;
            })->sum('amount');

            $pt        = $paymentTotals->get($student->id);
            $totalPaid = (float) ($pt->total_paid     ?? 0);
            $discount  = (float) ($pt->total_discount ?? 0);
            $balance   = max(0, $totalDue - $totalPaid - $discount);

            if ($balance <= 0) { $skippedNoBalance++; continue; }

            $parent = $student->studentParent;
            if (!$parent || !$parent->primary_phone) { $skippedNoPhone++; continue; }

            $notifier->notifyFeeDue($student, number_format($balance, 2, '.', ''), $dueDate);
            $sent++;
        }

        return response()->json([
            'success'             => true,
            'sent'                => $sent,
            'skipped_no_phone'    => $skippedNoPhone,
            'skipped_no_balance'  => $skippedNoBalance,
            'message'             => "Reminders queued for {$sent} parent(s)."
                                   . ($skippedNoPhone ? " {$skippedNoPhone} skipped (no phone)." : '')
                                   . ($skippedNoBalance ? " {$skippedNoBalance} skipped (no balance)." : ''),
        ]);
    }

    /**
     * Fee Summary Report
     * Shows S.No, Admission No, Student, Father, Class, Total Due, Concession, Payable, Paid, Balance.
     */
}
