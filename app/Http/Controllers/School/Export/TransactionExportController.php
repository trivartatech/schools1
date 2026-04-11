<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Traits\Exportable;
use Illuminate\Http\Request;

class TransactionExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $query = Transaction::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->with(['lines.ledger', 'createdBy'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('type'))   $query->where('type', $request->type);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('from'))   $query->where('date', '>=', $request->from);
        if ($request->filled('to'))     $query->where('date', '<=', $request->to);

        $transactions = $query->get();

        $headers = ['S.No', 'Txn #', 'Date', 'Type', 'Status', 'Narration', 'Ref #', 'Amount', 'Created By'];
        $totalAmount = 0;

        $rows = [];
        foreach ($transactions as $i => $t) {
            $amount = (float) $t->lines->where('type', 'debit')->sum('amount');
            $totalAmount += $amount;
            $rows[] = [
                $i + 1,
                $t->transaction_no,
                $t->date->toDateString(),
                ucfirst($t->type),
                ucfirst($t->status),
                $t->narration ?? '',
                $t->reference_no ?? '',
                number_format($amount, 2),
                $t->createdBy?->name ?? '',
            ];
        }

        $footer = ['', '', '', '', '', '', 'TOTAL', number_format($totalAmount, 2), ''];

        return $this->exportResponse($request, $headers, $rows, 'transactions-export-' . now()->format('Y-m-d'), [
            'footer'      => $footer,
            'orientation' => 'landscape',
        ]);
    }
}
