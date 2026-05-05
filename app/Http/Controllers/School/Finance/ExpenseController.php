<?php

namespace App\Http\Controllers\School\Finance;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $query = Expense::where('school_id', $schoolId)
                    ->where('academic_year_id', $academicYearId)
                    ->with(['category', 'recordedBy:id,name', 'glTransaction:id,transaction_no']);

        if ($request->filled('category_id')) {
            $query->where('expense_category_id', $request->category_id);
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('expense_date', [$request->from_date, $request->to_date]);
        }

        $expenses   = $query->orderByDesc('expense_date')->orderByDesc('id')->get();
        $categories = ExpenseCategory::where('school_id', $schoolId)->orderBy('name')->get();

        return Inertia::render('School/Finance/Expenses/Index', [
            'expenses'   => $expenses,
            'categories' => $categories,
            'filters'    => $request->only('category_id', 'from_date', 'to_date'),
        ]);
    }

    public function store(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $request->validate([
            // FIX #3: scope category to current school
            'expense_category_id' => [
                'required',
                Rule::exists('expense_categories', 'id')->where('school_id', $schoolId),
            ],
            'amount'          => 'required|numeric|min:0.01',
            // FIX #10: disallow future-dated expenses
            'expense_date'    => 'required|date|before_or_equal:today',
            'payment_mode'    => [
                'required', 'string',
                Rule::exists('payment_methods', 'code')
                    ->where('school_id', $schoolId)
                    ->where('is_active', true),
            ],
            'transaction_ref' => 'nullable|string|max:100',
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'attachment'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('expenses', 'public');
        }

        Expense::create([
            'school_id'           => $schoolId,
            'academic_year_id'    => $academicYearId,
            'expense_category_id' => $request->expense_category_id,
            'amount'              => $request->amount,
            'expense_date'        => $request->expense_date,
            'payment_mode'        => $request->payment_mode,
            'transaction_ref'     => $request->transaction_ref,
            'title'               => $request->title,
            'description'         => $request->description,
            'attachment_path'     => $path,
            'recorded_by'         => auth()->id(),
        ]);

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function update(Request $request, Expense $expense)
    {
        // FIX #1: tenant guard — prevent cross-school updates
        abort_unless($expense->school_id === app('current_school_id'), 403);

        $schoolId = $expense->school_id;

        $request->validate([
            // FIX #3: scope category to current school
            'expense_category_id' => [
                'required',
                Rule::exists('expense_categories', 'id')->where('school_id', $schoolId),
            ],
            'amount'          => 'required|numeric|min:0.01',
            // FIX #10: disallow future-dated expenses
            'expense_date'    => 'required|date|before_or_equal:today',
            'payment_mode'    => [
                'required', 'string',
                Rule::exists('payment_methods', 'code')
                    ->where('school_id', $schoolId)
                    ->where('is_active', true),
            ],
            'transaction_ref' => 'nullable|string|max:100',
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'attachment'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $updateData = $request->only([
            'expense_category_id', 'amount', 'expense_date', 'payment_mode',
            'transaction_ref', 'title', 'description',
        ]);

        if ($request->hasFile('attachment')) {
            if ($expense->attachment_path) {
                Storage::disk('public')->delete($expense->attachment_path);
            }
            $updateData['attachment_path'] = $request->file('attachment')->store('expenses', 'public');
        }

        $expense->update($updateData);

        return back()->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense)
    {
        // FIX #2: tenant guard — prevent cross-school deletes
        abort_unless($expense->school_id === app('current_school_id'), 403);

        if ($expense->attachment_path) {
            Storage::disk('public')->delete($expense->attachment_path);
        }
        $expense->delete();

        return back()->with('success', 'Expense deleted successfully.');
    }

    /**
     * POST /school/expenses/post-all-unposted
     * Batch-post all unposted expenses for the current school/year to GL.
     */
    public function postAllUnposted()
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $service        = app(\App\Services\GlPostingService::class);

        $expenses = Expense::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('gl_transaction_id')
            ->with('category')
            ->get();

        $posted = 0;
        foreach ($expenses as $expense) {
            $tx = $service->postExpense($expense);
            if ($tx) $posted++;
        }

        return back()->with('success', "Posted {$posted} of {$expenses->count()} unposted expenses to GL.");
    }

    /**
     * POST /school/expenses/{expense}/post-gl
     * Manually post an expense to the General Ledger
     */
    public function postGl(Expense $expense)
    {
        abort_unless($expense->school_id === app('current_school_id'), 403);

        $tx = app(\App\Services\GlPostingService::class)->repostExpense($expense);

        if ($tx) {
            return back()->with('success', 'Posted to GL: ' . $tx->transaction_no);
        }

        return back()->with('error', 'GL not configured. Please set up ledger mappings in GL Config.');
    }
}
