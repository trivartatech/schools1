<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Traits\Exportable;
use Illuminate\Http\Request;

class ExpenseExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $query = Expense::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->with(['category', 'recordedBy:id,first_name,last_name,name']);

        if ($request->filled('category_id')) {
            $query->where('expense_category_id', $request->category_id);
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('expense_date', [$request->from_date, $request->to_date]);
        }

        $expenses = $query->orderByDesc('expense_date')->get();

        $headers = ['S.No', 'Date', 'Title', 'Category', 'Amount', 'Payment Mode', 'Ref #', 'Recorded By'];
        $totalAmount = 0;

        $rows = [];
        foreach ($expenses as $i => $e) {
            $totalAmount += (float) $e->amount;
            $rows[] = [
                $i + 1,
                $e->expense_date,
                $e->title,
                $e->category?->name ?? '',
                number_format((float) $e->amount, 2),
                ucfirst(str_replace('_', ' ', $e->payment_mode)),
                $e->transaction_ref ?? '',
                $e->recordedBy?->name ?? '',
            ];
        }

        $footer = ['', '', '', 'TOTAL', number_format($totalAmount, 2), '', '', ''];

        return $this->exportResponse($request, $headers, $rows, 'expenses-export-' . now()->format('Y-m-d'), [
            'footer' => $footer,
        ]);
    }
}
