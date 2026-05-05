<?php

namespace App\Http\Controllers\School\Finance;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\FeePayment;
use App\Models\HostelFeePayment;
use App\Models\Payroll;
use App\Models\FeeHead;
use App\Models\StationaryFeePayment;
use App\Models\TransportFeePayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $academicYear = \App\Models\AcademicYear::find($academicYearId);

        // Parse date range
        $startDate = $request->input('start_date') ?: ($academicYear ? $academicYear->start_date->format('Y-m-d') : Carbon::now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->input('end_date') ?: ($academicYear ? $academicYear->end_date->format('Y-m-d') : Carbon::now()->endOfYear()->format('Y-m-d'));

        // 1. Total Fee Collected (with class/section filters)
        $feeQuery = FeePayment::where('school_id', $schoolId)->whereBetween('payment_date', [$startDate, $endDate]);
        if ($request->filled('class_id')) {
            $feeQuery->whereHas('student.currentAcademicHistory', function($q) use ($request, $academicYearId) {
                $q->where('class_id', $request->class_id);
                if ($request->filled('section_id')) {
                    $q->where('section_id', $request->section_id);
                }
                $q->where('academic_year_id', $academicYearId);
            });
        }

        $totalTuitionFees = (clone $feeQuery)->sum('amount_paid');

        // 1b. Total Transport Fee Collected (same date / class / section filters)
        $transportQuery = TransportFeePayment::where('school_id', $schoolId)
            ->whereBetween('payment_date', [$startDate, $endDate]);
        if ($request->filled('class_id')) {
            $transportQuery->whereHas('student.currentAcademicHistory', function($q) use ($request, $academicYearId) {
                $q->where('class_id', $request->class_id);
                if ($request->filled('section_id')) {
                    $q->where('section_id', $request->section_id);
                }
                $q->where('academic_year_id', $academicYearId);
            });
        }
        $totalTransportFees = (float) (clone $transportQuery)->sum('amount_paid');

        // 1c. Total Hostel Fee Collected (same date / class / section filters)
        $hostelQuery = HostelFeePayment::where('school_id', $schoolId)
            ->whereBetween('payment_date', [$startDate, $endDate]);
        if ($request->filled('class_id')) {
            $hostelQuery->whereHas('student.currentAcademicHistory', function($q) use ($request, $academicYearId) {
                $q->where('class_id', $request->class_id);
                if ($request->filled('section_id')) {
                    $q->where('section_id', $request->section_id);
                }
                $q->where('academic_year_id', $academicYearId);
            });
        }
        $totalHostelFees = (float) (clone $hostelQuery)->sum('amount_paid');

        // 1d. Total Stationary Fee Collected (same date / class / section filters)
        $stationaryQuery = StationaryFeePayment::where('school_id', $schoolId)
            ->whereBetween('payment_date', [$startDate, $endDate]);
        if ($request->filled('class_id')) {
            $stationaryQuery->whereHas('student.currentAcademicHistory', function($q) use ($request, $academicYearId) {
                $q->where('class_id', $request->class_id);
                if ($request->filled('section_id')) {
                    $q->where('section_id', $request->section_id);
                }
                $q->where('academic_year_id', $academicYearId);
            });
        }
        $totalStationaryFees = (float) (clone $stationaryQuery)->sum('amount_paid');

        $totalFees = (float) $totalTuitionFees + $totalTransportFees + $totalHostelFees + $totalStationaryFees;

        // 2. Total Expenses
        $totalExpenses = Expense::where('school_id', $schoolId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');

        // 3. Total Payroll (Status: paid)
        $totalPayroll = Payroll::where('school_id', $schoolId)
            ->where('status', 'paid')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('net_salary');

        $netRevenue = $totalFees - ($totalExpenses + $totalPayroll);

        // Chart Data: Monthly Income vs Expenses 
        // Use database-agnostic approach or raw query specific to SQLite based on connection type
        // The project is on MySQL 8.4 generally but tests/dev might be SQLite.
        $dbDriver = DB::connection()->getDriverName();
        $dateFormatter = $dbDriver === 'sqlite' ? "strftime('%Y-%m', payment_date)" : "DATE_FORMAT(payment_date, '%Y-%m')";
        $expenseDateFormatter = $dbDriver === 'sqlite' ? "strftime('%Y-%m', expense_date)" : "DATE_FORMAT(expense_date, '%Y-%m')";
        $payrollDateFormatter = $dbDriver === 'sqlite' ? "strftime('%Y-%m', payment_date)" : "DATE_FORMAT(payment_date, '%Y-%m')";

        // Grouping by the alias `month` breaks under MySQL sql_mode=only_full_group_by.
        // We group by the exact raw expression instead, which is always safe.
        $monthlyTuitionIncome = (clone $feeQuery)
            ->selectRaw("$dateFormatter as month, SUM(amount_paid) as total")
            ->groupByRaw($dateFormatter)
            ->pluck('total', 'month');

        $monthlyTransportIncome = (clone $transportQuery)
            ->selectRaw("$dateFormatter as month, SUM(amount_paid) as total")
            ->groupByRaw($dateFormatter)
            ->pluck('total', 'month');

        $monthlyHostelIncome = (clone $hostelQuery)
            ->selectRaw("$dateFormatter as month, SUM(amount_paid) as total")
            ->groupByRaw($dateFormatter)
            ->pluck('total', 'month');

        $monthlyStationaryIncome = (clone $stationaryQuery)
            ->selectRaw("$dateFormatter as month, SUM(amount_paid) as total")
            ->groupByRaw($dateFormatter)
            ->pluck('total', 'month');

        // Combine the income streams by month (same key format → same month bucket)
        $monthlyIncomeQuery = collect($monthlyTuitionIncome->keys())
            ->merge($monthlyTransportIncome->keys())
            ->merge($monthlyHostelIncome->keys())
            ->merge($monthlyStationaryIncome->keys())
            ->unique()
            ->mapWithKeys(fn($m) => [
                $m => (float) ($monthlyTuitionIncome[$m] ?? 0)
                    + (float) ($monthlyTransportIncome[$m] ?? 0)
                    + (float) ($monthlyHostelIncome[$m] ?? 0)
                    + (float) ($monthlyStationaryIncome[$m] ?? 0),
            ]);

        $monthlyExpensesQuery = Expense::where('school_id', $schoolId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->selectRaw("$expenseDateFormatter as month, SUM(amount) as total")
            ->groupByRaw($expenseDateFormatter)
            ->pluck('total', 'month');

        $monthlyPayrollQuery = Payroll::where('school_id', $schoolId)
            ->where('status', 'paid')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->selectRaw("$payrollDateFormatter as month, SUM(net_salary) as total")
            ->groupByRaw($payrollDateFormatter)
            ->pluck('total', 'month');

        // Extract all unique months
        $allMonths = collect($monthlyIncomeQuery->keys())
            ->merge($monthlyExpensesQuery->keys())
            ->merge($monthlyPayrollQuery->keys())
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Format for Chart.js
        $chartLabels = [];
        $incomeData = [];
        $expenseData = [];

        foreach ($allMonths as $month) {
            $chartLabels[] = Carbon::createFromFormat('Y-m', $month)->format('M y');
            $incomeData[] = $monthlyIncomeQuery->get($month, 0);
            $expenseData[] = $monthlyExpensesQuery->get($month, 0) + $monthlyPayrollQuery->get($month, 0);
        }

        $chartData = [
            'labels' => $chartLabels,
            'income' => $incomeData,
            'expense' => $expenseData,
        ];

        // Breakdown: Fees by Head
        $feesByHeadQuery = DB::table('fee_payments')
            ->join('fee_heads', 'fee_payments.fee_head_id', '=', 'fee_heads.id')
            ->where('fee_payments.school_id', $schoolId)
            ->whereBetween('fee_payments.payment_date', [$startDate, $endDate]);
            
        if ($request->filled('class_id')) {
            $feesByHeadQuery->join('student_academic_histories', function($join) use ($academicYearId) {
                $join->on('fee_payments.student_id', '=', 'student_academic_histories.student_id')
                     ->where('student_academic_histories.academic_year_id', '=', $academicYearId);
            });
            $feesByHeadQuery->where('student_academic_histories.class_id', $request->class_id);
            if ($request->filled('section_id')) {
                $feesByHeadQuery->where('student_academic_histories.section_id', $request->section_id);
            }
        }

        $feesByHead = $feesByHeadQuery
            ->select('fee_heads.name', DB::raw('SUM(amount_paid) as total'))
            ->groupBy('fee_heads.name')
            ->orderByDesc('total')
            ->get();

        // Surface transport collection as its own row in the breakdown so it
        // doesn't get hidden under "Tuition Fee" or vanish from the chart.
        if ($totalTransportFees > 0) {
            $feesByHead->push((object) [
                'name'  => 'Transport Fee',
                'total' => $totalTransportFees,
            ]);
            $feesByHead = $feesByHead->sortByDesc('total')->values();
        }

        // Same treatment for hostel collection.
        if ($totalHostelFees > 0) {
            $feesByHead->push((object) [
                'name'  => 'Hostel Fee',
                'total' => $totalHostelFees,
            ]);
            $feesByHead = $feesByHead->sortByDesc('total')->values();
        }

        // Same treatment for stationary collection.
        if ($totalStationaryFees > 0) {
            $feesByHead->push((object) [
                'name'  => 'Stationary Fee',
                'total' => $totalStationaryFees,
            ]);
            $feesByHead = $feesByHead->sortByDesc('total')->values();
        }

        // Breakdown: Top Expenses Category
        $topExpenseCategories = DB::table('expenses')
            ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->where('expenses.school_id', $schoolId)
            ->whereBetween('expenses.expense_date', [$startDate, $endDate])
            ->select('expense_categories.name', DB::raw('SUM(amount) as total'))
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
            
        // Map null category names to "Uncategorized"
        $topExpenseCategories->transform(function ($item) {
            if (!$item->name) $item->name = 'Uncategorized';
            return $item;
        });

        $classes = \App\Models\CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get();

        return Inertia::render('School/Finance/Reports/Index', [
            'metrics' => [
                'total_fees_collected'             => (float) $totalFees,
                'total_tuition_fees_collected'     => (float) $totalTuitionFees,
                'total_transport_fees_collected'   => (float) $totalTransportFees,
                'total_hostel_fees_collected'      => (float) $totalHostelFees,
                'total_stationary_fees_collected'  => (float) $totalStationaryFees,
                'total_expenses'                 => (float) $totalExpenses,
                'total_payroll'                  => (float) $totalPayroll,
                'net_revenue'                    => (float) $netRevenue,
            ],
            'chartData' => $chartData,
            'feesByHead' => $feesByHead,
            'topExpenseCategories' => $topExpenseCategories,
            'classes' => $classes,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'class_id' => $request->class_id,
                'section_id' => $request->section_id,
            ]
        ]);
    }
}
