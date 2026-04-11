<?php

namespace App\Http\Controllers\School\Finance;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BudgetController extends Controller
{
    public function index()
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        // Pre-fetch spent amounts per category in one SQL query
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
            ->get()
            ->map(function ($b) use ($spentPerCategory, $totalSpentAll) {
                // If no category assigned, use total school spend
                $spent = $b->expense_category_id
                    ? (float) ($spentPerCategory[$b->expense_category_id] ?? 0)
                    : (float) $totalSpentAll;

                $remaining = max(0, (float) $b->amount - $spent);
                $percent   = $b->amount > 0 ? min(100, round(($spent / $b->amount) * 100)) : 0;

                return array_merge($b->toArray(), [
                    'spent'     => $spent,
                    'remaining' => $remaining,
                    'percent'   => $percent,
                ]);
            });

        $categories = ExpenseCategory::where('school_id', $schoolId)->orderBy('name')->get();

        return Inertia::render('School/Finance/Budgets/Index', [
            'budgets'    => $budgets,
            'categories' => $categories,
            'totals'     => [
                'budget'    => (float) $budgets->sum('amount'),
                'spent'     => (float) $budgets->sum('spent'),
                'remaining' => (float) $budgets->sum('remaining'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'amount'              => 'required|numeric|min:1',
            'notes'               => 'nullable|string|max:500',
        ]);

        if (!empty($data['expense_category_id'])) {
            abort_unless(
                ExpenseCategory::where('id', $data['expense_category_id'])
                    ->where('school_id', $schoolId)->exists(),
                403
            );
        }

        Budget::create(array_merge($data, [
            'school_id'        => $schoolId,
            'academic_year_id' => $academicYearId,
        ]));

        return back()->with('success', 'Budget created.');
    }

    public function update(Request $request, Budget $budget)
    {
        $this->gate($budget);

        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'amount'              => 'required|numeric|min:1',
            'notes'               => 'nullable|string|max:500',
        ]);

        $budget->update($data);
        return back()->with('success', 'Budget updated.');
    }

    public function destroy(Budget $budget)
    {
        $this->gate($budget);
        $budget->delete();
        return back()->with('success', 'Budget deleted.');
    }

    private function gate(Budget $budget): void
    {
        abort_unless($budget->school_id === app('current_school_id'), 403);
    }
}
