<?php

namespace App\Http\Controllers\School\Finance;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class FinancialStatementsController extends Controller
{
    // ─── Trial Balance ────────────────────────────────────────────
    public function trialBalance(Request $request)
    {
        $schoolId = app('current_school_id');
        $asOf     = $request->input('as_of', now()->toDateString());

        $ledgers = Ledger::where('school_id', $schoolId)
            ->with('ledgerType')
            ->orderBy('name')
            ->get();

        $rows        = [];
        $totalDebit  = 0.0;
        $totalCredit = 0.0;

        foreach ($ledgers as $ledger) {
            $openDebit  = $ledger->opening_balance_type === 'debit'  ? (float) $ledger->opening_balance : 0;
            $openCredit = $ledger->opening_balance_type === 'credit' ? (float) $ledger->opening_balance : 0;

            $lines = $ledger->transactionLines()
                ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
                ->where('transactions.date', '<=', $asOf)
                ->where('transactions.school_id', $schoolId)
                ->where('transactions.status', 'posted')
                ->selectRaw('transaction_lines.type, SUM(transaction_lines.amount) as total')
                ->groupBy('transaction_lines.type')
                ->pluck('total', 'type');

            $txDebit  = (float) ($lines['debit']  ?? 0);
            $txCredit = (float) ($lines['credit'] ?? 0);

            $net = ($openDebit + $txDebit) - ($openCredit + $txCredit);

            $debitBal  = $net >= 0 ? round($net, 2)       : 0;
            $creditBal = $net <  0 ? round(abs($net), 2)  : 0;

            if ($debitBal > 0 || $creditBal > 0) {
                $rows[] = [
                    'ledger_id'   => $ledger->id,
                    'ledger_name' => $ledger->name,
                    'type_name'   => $ledger->ledgerType?->name ?? '—',
                    'debit'       => $debitBal,
                    'credit'      => $creditBal,
                ];
                $totalDebit  += $debitBal;
                $totalCredit += $creditBal;
            }
        }

        if ($request->input('export') === 'csv') {
            $data = [['Ledger Account', 'Ledger Type', 'Debit (Dr) ₹', 'Credit (Cr) ₹']];
            foreach ($rows as $r) {
                $data[] = [$r['ledger_name'], $r['type_name'], $r['debit'], $r['credit']];
            }
            $data[] = ['', 'TOTAL', round($totalDebit, 2), round($totalCredit, 2)];
            return $this->csvResponse($data, 'trial-balance-' . $asOf . '.csv');
        }

        if ($request->input('export') === 'pdf') {
            $pdf = Pdf::loadView('pdf.trial-balance', [
                'rows'        => $rows,
                'totalDebit'  => round($totalDebit, 2),
                'totalCredit' => round($totalCredit, 2),
                'asOf'        => $asOf,
            ])->setPaper('a4', 'portrait');
            return $pdf->download('trial-balance-' . $asOf . '.pdf');
        }

        return Inertia::render('School/Finance/Reports/TrialBalance', [
            'rows'       => $rows,
            'totals'     => ['debit' => round($totalDebit, 2), 'credit' => round($totalCredit, 2)],
            'isBalanced' => abs($totalDebit - $totalCredit) < 0.01,
            'asOf'       => $asOf,
        ]);
    }

    // ─── Profit & Loss ────────────────────────────────────────────
    public function profitLoss(Request $request)
    {
        $schoolId = app('current_school_id');
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        $ledgers = Ledger::where('school_id', $schoolId)
            ->with('ledgerType')
            ->whereHas('ledgerType', fn ($q) => $q->whereIn('name', ['Income', 'Expense']))
            ->orderBy('name')
            ->get();

        $income        = [];
        $expenses      = [];
        $totalIncome   = 0.0;
        $totalExpenses = 0.0;

        foreach ($ledgers as $ledger) {
            $lines = $ledger->transactionLines()
                ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
                ->whereBetween('transactions.date', [$from, $to])
                ->where('transactions.school_id', $schoolId)
                ->where('transactions.status', 'posted')
                ->selectRaw('transaction_lines.type, SUM(transaction_lines.amount) as total')
                ->groupBy('transaction_lines.type')
                ->pluck('total', 'type');

            $debit  = (float) ($lines['debit']  ?? 0);
            $credit = (float) ($lines['credit'] ?? 0);
            $nature = $ledger->ledgerType?->nature; // 'credit' for Income, 'debit' for Expense
            $net    = $nature === 'credit' ? ($credit - $debit) : ($debit - $credit);

            $entry = [
                'ledger_id'   => $ledger->id,
                'ledger_name' => $ledger->name,
                'amount'      => round($net, 2),
            ];

            if ($ledger->ledgerType?->name === 'Income') {
                $income[]     = $entry;
                $totalIncome += max(0.0, $net);
            } else {
                $expenses[]    = $entry;
                $totalExpenses += max(0.0, $net);
            }
        }

        $netSurplus = $totalIncome - $totalExpenses;

        if ($request->input('export') === 'csv') {
            $data = [['Section', 'Ledger Account', 'Amount (₹)']];
            foreach ($income   as $r) $data[] = ['Income',  $r['ledger_name'], $r['amount']];
            $data[] = ['', 'Total Income',   round($totalIncome, 2)];
            $data[] = ['', '', ''];
            foreach ($expenses as $r) $data[] = ['Expense', $r['ledger_name'], $r['amount']];
            $data[] = ['', 'Total Expenses', round($totalExpenses, 2)];
            $data[] = ['', '', ''];
            $label  = $netSurplus >= 0 ? 'Net Surplus' : 'Net Deficit';
            $data[] = ['', $label, round(abs($netSurplus), 2)];
            return $this->csvResponse($data, 'profit-loss-' . $from . '-to-' . $to . '.csv');
        }

        if ($request->input('export') === 'pdf') {
            $pdf = Pdf::loadView('pdf.profit-loss', [
                'income'        => $income,
                'expenses'      => $expenses,
                'totalIncome'   => round($totalIncome, 2),
                'totalExpenses' => round($totalExpenses, 2),
                'netSurplus'    => round($netSurplus, 2),
                'from'          => $from,
                'to'            => $to,
            ])->setPaper('a4', 'portrait');
            return $pdf->download('profit-loss-' . $from . '-to-' . $to . '.pdf');
        }

        return Inertia::render('School/Finance/Reports/ProfitLoss', [
            'income'        => $income,
            'expenses'      => $expenses,
            'totalIncome'   => round($totalIncome, 2),
            'totalExpenses' => round($totalExpenses, 2),
            'netSurplus'    => round($netSurplus, 2),
            'from'          => $from,
            'to'            => $to,
        ]);
    }

    // ─── Balance Sheet ────────────────────────────────────────────
    public function balanceSheet(Request $request)
    {
        $schoolId = app('current_school_id');
        $asOf     = $request->input('as_of', now()->toDateString());

        $ledgers = Ledger::where('school_id', $schoolId)
            ->with('ledgerType')
            ->whereHas('ledgerType', fn ($q) => $q->whereIn('name', ['Asset', 'Liability', 'Capital']))
            ->orderBy('name')
            ->get();

        $assets      = [];
        $liabilities = [];
        $capital     = [];

        $totalAssets      = 0.0;
        $totalLiabilities = 0.0;
        $totalCapital     = 0.0;

        foreach ($ledgers as $ledger) {
            $lines = $ledger->transactionLines()
                ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
                ->where('transactions.date', '<=', $asOf)
                ->where('transactions.school_id', $schoolId)
                ->where('transactions.status', 'posted')
                ->selectRaw('transaction_lines.type, SUM(transaction_lines.amount) as total')
                ->groupBy('transaction_lines.type')
                ->pluck('total', 'type');

            $txDebit  = (float) ($lines['debit']  ?? 0);
            $txCredit = (float) ($lines['credit'] ?? 0);

            $openDebit  = $ledger->opening_balance_type === 'debit'  ? (float) $ledger->opening_balance : 0;
            $openCredit = $ledger->opening_balance_type === 'credit' ? (float) $ledger->opening_balance : 0;

            $net    = ($openDebit + $txDebit) - ($openCredit + $txCredit);
            $nature = $ledger->ledgerType?->nature;
            // Debit-nature (Asset): positive net means value
            // Credit-nature (Liability/Capital): negative net means value
            $amount = $nature === 'debit' ? $net : -$net;

            $entry = [
                'ledger_id'   => $ledger->id,
                'ledger_name' => $ledger->name,
                'amount'      => round($amount, 2),
            ];

            match ($ledger->ledgerType?->name) {
                'Asset'     => [$assets[]      = $entry, $totalAssets      += $amount],
                'Liability' => [$liabilities[] = $entry, $totalLiabilities += $amount],
                default     => [$capital[]     = $entry, $totalCapital     += $amount],
            };
        }

        $totalLCE = $totalLiabilities + $totalCapital;

        if ($request->input('export') === 'csv') {
            $data = [['Section', 'Ledger Account', 'Amount (₹)']];
            foreach ($assets      as $r) $data[] = ['Asset',     $r['ledger_name'], $r['amount']];
            $data[] = ['', 'Total Assets',               round($totalAssets, 2)];
            $data[] = ['', '', ''];
            foreach ($liabilities as $r) $data[] = ['Liability', $r['ledger_name'], $r['amount']];
            $data[] = ['', 'Total Liabilities',          round($totalLiabilities, 2)];
            $data[] = ['', '', ''];
            foreach ($capital     as $r) $data[] = ['Capital',   $r['ledger_name'], $r['amount']];
            $data[] = ['', 'Total Capital',              round($totalCapital, 2)];
            $data[] = ['', 'Total Liabilities + Capital',round($totalLCE, 2)];
            return $this->csvResponse($data, 'balance-sheet-' . $asOf . '.csv');
        }

        if ($request->input('export') === 'pdf') {
            $pdf = Pdf::loadView('pdf.balance-sheet', [
                'assets'           => $assets,
                'liabilities'      => $liabilities,
                'capital'          => $capital,
                'totalAssets'      => round($totalAssets, 2),
                'totalLiabilities' => round($totalLiabilities, 2),
                'totalCapital'     => round($totalCapital, 2),
                'totalLCE'         => round($totalLCE, 2),
                'asOf'             => $asOf,
            ])->setPaper('a4', 'portrait');
            return $pdf->download('balance-sheet-' . $asOf . '.pdf');
        }

        return Inertia::render('School/Finance/Reports/BalanceSheet', [
            'assets'           => $assets,
            'liabilities'      => $liabilities,
            'capital'          => $capital,
            'totalAssets'      => round($totalAssets, 2),
            'totalLiabilities' => round($totalLiabilities, 2),
            'totalCapital'     => round($totalCapital, 2),
            'totalLCE'         => round($totalLCE, 2),
            'isBalanced'       => abs($totalAssets - $totalLCE) < 0.01,
            'asOf'             => $asOf,
        ]);
    }

    // ─── CSV helper ───────────────────────────────────────────────
    private function csvResponse(array $rows, string $filename)
    {
        $handle = fopen('php://memory', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return Response::make($content, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
