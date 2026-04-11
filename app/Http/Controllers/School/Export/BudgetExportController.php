<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Traits\Exportable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $spentPerCategory = DB::table('expenses')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->selectRaw('expense_category_id, SUM(amount) as spent')
            ->groupBy('expense_category_id')
            ->pluck('spent', 'expense_category_id');

        $totalSpentAll = DB::table('expenses')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->sum('amount');

        $budgets = Budget::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->with('expenseCategory')
            ->orderBy('name')
            ->get();

        $headers = ['S.No', 'Budget Name', 'Category', 'Budget Amount', 'Spent', 'Remaining', 'Utilization %'];
        $totalBudget = 0; $totalSpent = 0; $totalRemaining = 0;

        $rows = [];
        foreach ($budgets as $i => $b) {
            $spent = $b->expense_category_id
                ? (float) ($spentPerCategory[$b->expense_category_id] ?? 0)
                : (float) $totalSpentAll;
            $remaining = max(0, (float) $b->amount - $spent);
            $percent   = $b->amount > 0 ? min(100, round(($spent / $b->amount) * 100)) : 0;

            $totalBudget    += (float) $b->amount;
            $totalSpent     += $spent;
            $totalRemaining += $remaining;

            $rows[] = [
                $i + 1,
                $b->name,
                $b->expenseCategory?->name ?? 'Overall',
                number_format((float) $b->amount, 2),
                number_format($spent, 2),
                number_format($remaining, 2),
                $percent . '%',
            ];
        }

        $overallPercent = $totalBudget > 0 ? min(100, round(($totalSpent / $totalBudget) * 100)) : 0;
        $footer = ['', 'TOTAL', '', number_format($totalBudget, 2), number_format($totalSpent, 2), number_format($totalRemaining, 2), $overallPercent . '%'];

        return $this->exportResponse($request, $headers, $rows, 'budgets-export-' . now()->format('Y-m-d'), [
            'footer' => $footer,
        ]);
    }
}
