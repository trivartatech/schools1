<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Traits\Exportable;
use Illuminate\Http\Request;

class LedgerExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request)
    {
        $schoolId = app('current_school_id');

        $ledgers = Ledger::where('school_id', $schoolId)
            ->with('ledgerType')
            ->withCount('transactionLines')
            ->orderBy('name')
            ->get()
            ->map(function ($ledger) {
                $bal = $ledger->getCurrentBalance();
                return array_merge($ledger->toArray(), [
                    'balance'      => $bal['amount'],
                    'balance_type' => $bal['type'],
                ]);
            });

        $headers = ['S.No', 'Ledger Name', 'Code', 'Type', 'Opening Balance', 'Opening Type', 'Current Balance', 'Balance Type', 'Transactions', 'Status'];

        $rows = [];
        foreach ($ledgers as $i => $l) {
            $rows[] = [
                $i + 1,
                $l['name'],
                $l['code'] ?? '',
                $l['ledger_type']['name'] ?? '',
                number_format((float) $l['opening_balance'], 2),
                ucfirst($l['opening_balance_type'] ?? ''),
                number_format((float) $l['balance'], 2),
                $l['balance_type'],
                $l['transaction_lines_count'],
                $l['is_active'] ? 'Active' : 'Inactive',
            ];
        }

        return $this->exportResponse($request, $headers, $rows, 'ledgers-export-' . now()->format('Y-m-d'), [
            'orientation' => 'landscape',
        ]);
    }
}
