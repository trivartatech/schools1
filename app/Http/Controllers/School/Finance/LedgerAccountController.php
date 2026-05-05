<?php

namespace App\Http\Controllers\School\Finance;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\LedgerType;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class LedgerAccountController extends Controller
{
    public function index()
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

        $types = LedgerType::where('school_id', $schoolId)->orderBy('name')->get();

        return Inertia::render('School/Finance/Ledgers/Index', [
            'ledgers' => $ledgers,
            'types'   => $types,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $data = $request->validate([
            'ledger_type_id'        => 'required|exists:ledger_types,id',
            'name'                  => 'required|string|max:150',
            'code'                  => 'nullable|string|max:20',
            'opening_balance'       => 'nullable|numeric|min:0',
            'opening_balance_type'  => 'required|in:debit,credit',
            'description'           => 'nullable|string|max:500',
        ]);

        // Ensure type belongs to this school
        abort_unless(
            LedgerType::where('id', $data['ledger_type_id'])->where('school_id', $schoolId)->exists(),
            403
        );

        if (Ledger::where('school_id', $schoolId)->where('name', $data['name'])->exists()) {
            return back()->withErrors(['name' => 'A ledger with this name already exists.']);
        }

        Ledger::create(array_merge($data, [
            'school_id'       => $schoolId,
            'opening_balance' => $data['opening_balance'] ?? 0,
            'is_system'       => false,
            'is_active'       => true,
        ]));

        return back()->with('success', 'Ledger created.');
    }

    public function show(Ledger $ledger, Request $request)
    {
        $this->guardTenant($ledger);

        $schoolId = app('current_school_id');

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        // FIX #8: filter by date at DB level (join transactions) instead of loading
        // ALL lines and filtering in PHP — avoids full-table scans on busy ledgers.
        // FIX #5: include transaction_id (integer) so Vue can build correct links.
        $lines = $ledger->transactionLines()
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->whereDate('transactions.date', '>=', $from)
            ->whereDate('transactions.date', '<=', $to)
            ->select(
                'transaction_lines.id',
                'transaction_lines.transaction_id',
                'transaction_lines.type',
                'transaction_lines.amount',
                'transaction_lines.description',
                'transactions.date',
                'transactions.transaction_no',
                'transactions.type as txn_type',
                'transactions.narration'
            )
            ->orderBy('transactions.date')
            ->orderBy('transaction_lines.id')
            ->get()
            ->map(fn($line) => [
                'id'             => $line->id,
                'transaction_id' => $line->transaction_id,   // integer ID for route link
                'date'           => $line->date,
                'transaction_no' => $line->transaction_no,
                'type_label'     => ucfirst($line->txn_type),
                'narration'      => $line->narration ?? $line->description,
                'debit'          => $line->type === 'debit'  ? (float) $line->amount : null,
                'credit'         => $line->type === 'credit' ? (float) $line->amount : null,
            ])
            ->values();

        // Running balance
        $runningBalance = $ledger->opening_balance_type === 'debit'
            ? (float) $ledger->opening_balance
            : -(float) $ledger->opening_balance;

        $rows = [];
        foreach ($lines as $line) {
            $runningBalance += ($line['debit'] ?? 0) - ($line['credit'] ?? 0);
            $rows[] = array_merge($line, [
                'running_balance'      => abs($runningBalance),
                'running_balance_type' => $runningBalance >= 0 ? 'Dr' : 'Cr',
            ]);
        }

        $bal = $ledger->getCurrentBalance();

        $ledgerData = array_merge($ledger->load('ledgerType')->toArray(), [
            'balance'      => $bal['amount'],
            'balance_type' => $bal['type'],
        ]);

        // CSV export
        if ($request->input('export') === 'csv') {
            $handle = fopen('php://memory', 'r+');
            fputcsv($handle, ['Date', 'Txn #', 'Narration', 'Debit (Dr) ₹', 'Credit (Cr) ₹', 'Balance', 'Dr/Cr']);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r['date'], $r['transaction_no'], $r['narration'] ?? '',
                    $r['debit'] ?? '', $r['credit'] ?? '',
                    $r['running_balance'], $r['running_balance_type'],
                ]);
            }
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);
            return Response::make($csv, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="ledger-' . str_replace(' ', '-', strtolower($ledger->name)) . '-' . $from . '-to-' . $to . '.csv"',
            ]);
        }

        // PDF export
        if ($request->input('export') === 'pdf') {
            $pdf = Pdf::loadView('pdf.ledger-book', [
                'ledger'  => $ledgerData,
                'rows'    => $rows,
                'from'    => $from,
                'to'      => $to,
            ])->setPaper('a4', 'landscape');
            return $pdf->download('ledger-' . str_replace(' ', '-', strtolower($ledger->name)) . '-' . $from . '-to-' . $to . '.pdf');
        }

        return Inertia::render('School/Finance/Ledgers/Show', [
            'ledger'  => $ledgerData,
            'rows'    => $rows,
            'filters' => compact('from', 'to'),
        ]);
    }

    public function update(Request $request, Ledger $ledger)
    {
        $this->guardTenant($ledger);

        $data = $request->validate([
            'ledger_type_id'        => 'required|exists:ledger_types,id',
            'name'                  => 'required|string|max:150',
            'code'                  => 'nullable|string|max:20',
            'opening_balance'       => 'nullable|numeric|min:0',
            'opening_balance_type'  => 'required|in:debit,credit',
            'description'           => 'nullable|string|max:500',
            'is_active'             => 'boolean',
        ]);

        // Ensure the selected type belongs to this school
        abort_unless(
            LedgerType::where('id', $data['ledger_type_id'])->where('school_id', $ledger->school_id)->exists(),
            403
        );

        $exists = Ledger::where('school_id', $ledger->school_id)
            ->where('name', $data['name'])
            ->where('id', '!=', $ledger->id)
            ->exists();
        if ($exists) {
            return back()->withErrors(['name' => 'A ledger with this name already exists.']);
        }

        $ledger->update($data);

        return back()->with('success', 'Ledger updated.');
    }

    public function destroy(Ledger $ledger)
    {
        $this->guardTenant($ledger);

        if ($ledger->is_system) {
            return back()->withErrors(['error' => 'System ledgers cannot be deleted.']);
        }

        if ($ledger->transactionLines()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete — this ledger has transaction entries.']);
        }

        $ledger->delete();

        return back()->with('success', 'Ledger deleted.');
    }

    private function guardTenant(Ledger $ledger): void
    {
        abort_unless($ledger->school_id === app('current_school_id'), 403);
    }
}
