<?php

namespace App\Http\Controllers\School\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $schoolId   = app('current_school_id');
        $categories = ExpenseCategory::where('school_id', $schoolId)->orderBy('name')->get();
        return Inertia::render('School/Finance/ExpenseCategories/Index', ['categories' => $categories]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');
        $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('expense_categories')->where('school_id', $schoolId),
            ],
            'description' => 'nullable|string',
        ]);

        ExpenseCategory::create([
            'school_id'   => $schoolId,
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return back()->with('success', 'Expense Category created successfully.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        // FIX #4: tenant guard — prevent cross-school updates
        abort_unless($expenseCategory->school_id === app('current_school_id'), 403);

        $schoolId = $expenseCategory->school_id;

        $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('expense_categories')
                    ->where('school_id', $schoolId)
                    ->ignore($expenseCategory->id),
            ],
            'description' => 'nullable|string',
        ]);

        $expenseCategory->update([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return back()->with('success', 'Expense Category updated successfully.');
    }

    public function destroy(ExpenseCategory $expenseCategory)
    {
        // FIX #4: tenant guard — prevent cross-school deletes
        abort_unless($expenseCategory->school_id === app('current_school_id'), 403);

        if (\App\Models\Expense::where('expense_category_id', $expenseCategory->id)->exists()) {
            return back()->withErrors(['error' => 'Cannot delete — this category is used in existing expenses.']);
        }

        $expenseCategory->delete();
        return back()->with('success', 'Expense Category deleted successfully.');
    }
}
