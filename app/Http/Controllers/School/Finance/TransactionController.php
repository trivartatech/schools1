<?php

namespace App\Http\Controllers\School\Finance;

use App\Http\Controllers\Controller;
use App\Models\FeePayment;
use App\Models\HostelFeePayment;
use App\Models\Ledger;
use App\Models\StationaryFeePayment;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\TransportFeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $query = Transaction::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->with(['lines.ledger', 'createdBy'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('type'))   $query->where('type',   $request->type);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('from'))   $query->where('date', '>=', $request->from);
        if ($request->filled('to'))     $query->where('date', '<=', $request->to);

        $mapTxn = fn ($t) => [
            'id'             => $t->id,
            'transaction_no' => $t->transaction_no,
            'date'           => $t->date->toDateString(),
            'type'           => $t->type,
            'status'         => $t->status,
            'narration'      => $t->narration,
            'reference_no'   => $t->reference_no,
            'total_amount'   => (float) $t->lines->where('type', 'debit')->sum('amount'),
            'lines_count'    => $t->lines->count(),
            'created_by'     => $t->createdBy?->name,
            'is_balanced'    => $t->getIsBalancedAttribute(),
        ];

        // CSV export needs all rows (no pagination)
        $transactions = $request->input('export') === 'csv'
            ? $query->get()->map($mapTxn)
            : $query->paginate(50)->through($mapTxn);

        // CSV export
        if ($request->input('export') === 'csv') {
            $handle = fopen('php://memory', 'r+');
            fputcsv($handle, ['Txn #', 'Date', 'Type', 'Status', 'Narration', 'Ref #', 'Amount (₹)', 'By']);
            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t['transaction_no'], $t['date'], $t['type'], $t['status'],
                    $t['narration'] ?? '', $t['reference_no'] ?? '',
                    $t['total_amount'], $t['created_by'] ?? '',
                ]);
            }
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            return Response::make($csv, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="transactions.csv"',
            ]);
        }

        $ledgers = Ledger::where('school_id', $schoolId)->where('is_active', true)
            ->with('ledgerType')->orderBy('name')->get();

        return Inertia::render('School/Finance/Transactions/Index', [
            'transactions' => $transactions,
            'ledgers'      => $ledgers,
            'filters'      => $request->only('type', 'status', 'from', 'to'),
            'perPage'      => 50,
        ]);
    }

    public function create()
    {
        $schoolId = app('current_school_id');

        $ledgers = Ledger::where('school_id', $schoolId)->where('is_active', true)
            ->with('ledgerType')->orderBy('name')->get();

        return Inertia::render('School/Finance/Transactions/Create', [
            'ledgers'  => $ledgers,
            'editMode' => false,
            'today'    => now()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $data = $request->validate([
            'date'         => 'required|date|before_or_equal:today',
            'type'         => 'required|in:journal,receipt,payment,contra',
            'status'       => 'in:draft,posted',
            'narration'    => 'nullable|string|max:500',
            'reference_no' => 'nullable|string|max:100',
            'lines'        => 'required|array|min:2',
            'lines.*.ledger_id'   => 'required|exists:ledgers,id',
            'lines.*.type'        => 'required|in:debit,credit',
            'lines.*.amount'      => 'required|numeric|min:0.01',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        $ledgerIds = collect($data['lines'])->pluck('ledger_id')->unique();
        $valid = Ledger::where('school_id', $schoolId)->whereIn('id', $ledgerIds)->count();
        if ($valid !== $ledgerIds->count()) {
            return back()->withErrors(['lines' => 'Invalid ledger selected.']);
        }

        $debitTotal  = collect($data['lines'])->where('type', 'debit')->sum('amount');
        $creditTotal = collect($data['lines'])->where('type', 'credit')->sum('amount');
        if (abs($debitTotal - $creditTotal) > 0.01) {
            return back()->withErrors(['lines' => 'Transaction is not balanced. Debit (' . $debitTotal . ') ≠ Credit (' . $creditTotal . ').']);
        }

        $status = $data['status'] ?? 'posted';

        DB::transaction(function () use ($data, $schoolId, $academicYearId, $status) {
            $transaction = Transaction::create([
                'school_id'        => $schoolId,
                'academic_year_id' => $academicYearId,
                'transaction_no'   => Transaction::generateNo($schoolId),
                'date'             => $data['date'],
                'type'             => $data['type'],
                'status'           => $status,
                'narration'        => $data['narration']    ?? null,
                'reference_no'     => $data['reference_no'] ?? null,
                'created_by'       => auth()->id(),
            ]);

            foreach ($data['lines'] as $line) {
                TransactionLine::create([
                    'transaction_id' => $transaction->id,
                    'ledger_id'      => $line['ledger_id'],
                    'type'           => $line['type'],
                    'amount'         => $line['amount'],
                    'description'    => $line['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('school.finance.transactions.index')
            ->with('success', 'Transaction recorded successfully.');
    }

    public function show(Transaction $transaction)
    {
        $this->gate($transaction);
        $transaction->load(['lines.ledger.ledgerType', 'createdBy', 'academicYear', 'reversalOf']);

        // Check if a reversal exists for this transaction
        $isReversed = Transaction::where('reversal_of', $transaction->id)->exists();
        $reversalTxn = Transaction::where('reversal_of', $transaction->id)->first();

        return Inertia::render('School/Finance/Transactions/Show', [
            'transaction' => array_merge($transaction->toArray(), [
                'date'         => $transaction->date->toDateString(),
                'total_amount' => (float) $transaction->lines->where('type', 'debit')->sum('amount'),
                'is_balanced'  => $transaction->getIsBalancedAttribute(),
                'is_reversed'  => $isReversed,
                'reversal_txn' => $reversalTxn ? ['id' => $reversalTxn->id, 'transaction_no' => $reversalTxn->transaction_no] : null,
            ]),
        ]);
    }

    public function edit(Transaction $transaction)
    {
        $this->gate($transaction);

        if ($transaction->status === 'void') {
            return back()->withErrors(['error' => 'Voided transactions cannot be edited.']);
        }

        $schoolId = app('current_school_id');
        $transaction->load(['lines.ledger']);
        $ledgers = Ledger::where('school_id', $schoolId)->where('is_active', true)
            ->with('ledgerType')->orderBy('name')->get();

        return Inertia::render('School/Finance/Transactions/Create', [
            'ledgers'     => $ledgers,
            'editMode'    => true,
            'transaction' => array_merge($transaction->toArray(), [
                'date' => $transaction->date->toDateString(),
            ]),
            'today' => now()->toDateString(),
        ]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $this->gate($transaction);

        if ($transaction->status === 'void') {
            return back()->withErrors(['error' => 'Voided transactions cannot be edited.']);
        }

        $schoolId = app('current_school_id');

        $data = $request->validate([
            'date'         => 'required|date|before_or_equal:today',
            'type'         => 'required|in:journal,receipt,payment,contra',
            'status'       => 'in:draft,posted',
            'narration'    => 'nullable|string|max:500',
            'reference_no' => 'nullable|string|max:100',
            'lines'        => 'required|array|min:2',
            'lines.*.ledger_id'   => 'required|exists:ledgers,id',
            'lines.*.type'        => 'required|in:debit,credit',
            'lines.*.amount'      => 'required|numeric|min:0.01',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        // Ensure all ledgers belong to this school (update has no secondary check unlike store)
        $ledgerIds = collect($data['lines'])->pluck('ledger_id')->unique();
        $valid = Ledger::where('school_id', $schoolId)->whereIn('id', $ledgerIds)->count();
        if ($valid !== $ledgerIds->count()) {
            return back()->withErrors(['lines' => 'Invalid ledger selected.']);
        }

        $debitTotal  = collect($data['lines'])->where('type', 'debit')->sum('amount');
        $creditTotal = collect($data['lines'])->where('type', 'credit')->sum('amount');
        if (abs($debitTotal - $creditTotal) > 0.01) {
            return back()->withErrors(['lines' => 'Transaction is not balanced.']);
        }

        DB::transaction(function () use ($transaction, $data) {
            $transaction->update([
                'date'         => $data['date'],
                'type'         => $data['type'],
                'status'       => $data['status'] ?? $transaction->status,
                'narration'    => $data['narration']    ?? null,
                'reference_no' => $data['reference_no'] ?? null,
            ]);

            $transaction->lines()->delete();
            foreach ($data['lines'] as $line) {
                TransactionLine::create([
                    'transaction_id' => $transaction->id,
                    'ledger_id'      => $line['ledger_id'],
                    'type'           => $line['type'],
                    'amount'         => $line['amount'],
                    'description'    => $line['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('school.finance.transactions.index')
            ->with('success', 'Transaction updated.');
    }

    public function destroy(Transaction $transaction)
    {
        $this->gate($transaction);

        if ($transaction->status === 'void') {
            return back()->withErrors(['error' => 'Voided transactions cannot be deleted.']);
        }

        // Clear gl_transaction_id on any fee payments linked to this transaction (all streams)
        FeePayment::where('gl_transaction_id', $transaction->id)->update(['gl_transaction_id' => null]);
        TransportFeePayment::where('gl_transaction_id', $transaction->id)->update(['gl_transaction_id' => null]);
        HostelFeePayment::where('gl_transaction_id', $transaction->id)->update(['gl_transaction_id' => null]);
        StationaryFeePayment::where('gl_transaction_id', $transaction->id)->update(['gl_transaction_id' => null]);

        $transaction->delete();
        return back()->with('success', 'Transaction deleted.');
    }

    // ── Reverse a transaction ────────────────────────────────────
    public function reverse(Transaction $transaction)
    {
        $this->gate($transaction);

        if ($transaction->status !== 'posted') {
            return back()->withErrors(['error' => 'Only posted transactions can be reversed.']);
        }

        if (Transaction::where('reversal_of', $transaction->id)->exists()) {
            return back()->withErrors(['error' => 'This transaction has already been reversed.']);
        }

        $transaction->load('lines');

        DB::transaction(function () use ($transaction) {
            $reversal = Transaction::create([
                'school_id'        => $transaction->school_id,
                'academic_year_id' => $transaction->academic_year_id,
                'transaction_no'   => Transaction::generateNo($transaction->school_id),
                'date'             => now()->toDateString(),
                'type'             => $transaction->type,
                'status'           => 'posted',
                'reversal_of'      => $transaction->id,
                'narration'        => 'REVERSAL of ' . $transaction->transaction_no
                    . ($transaction->narration ? ': ' . $transaction->narration : ''),
                'reference_no'     => $transaction->transaction_no,
                'created_by'       => auth()->id(),
            ]);

            // Flip all lines: debit ↔ credit
            foreach ($transaction->lines as $line) {
                TransactionLine::create([
                    'transaction_id' => $reversal->id,
                    'ledger_id'      => $line->ledger_id,
                    'type'           => $line->type === 'debit' ? 'credit' : 'debit',
                    'amount'         => $line->amount,
                    'description'    => 'Reversal',
                ]);
            }

            // Void the original
            $transaction->update(['status' => 'void']);
        });

        return back()->with('success', 'Transaction reversed. Original marked as void.');
    }

    // ── Guard ────────────────────────────────────────────────────
    private function gate(Transaction $tx): void
    {
        abort_unless($tx->school_id === app('current_school_id'), 403);
    }
}
