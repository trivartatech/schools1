<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FeePayment;
use App\Models\HostelFeePayment;
use App\Models\Ledger;
use App\Models\LedgerType;
use App\Models\Payroll;
use App\Models\School;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\TransportFeePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Central service for posting operational records (fee payments, expenses, payroll)
 * to the General Ledger.
 *
 * Each method is idempotent — it silently returns null if the record is already
 * posted (gl_transaction_id is set) or if GL is not configured for the school.
 */
class GlPostingService
{
    // ── Fee Payment ───────────────────────────────────────────────────────────

    /**
     * Dr  Cash/Bank ledger        (gl_cash_ledger_id)
     * Cr  Fee Income ledger       (gl_fee_income_ledger_id)
     */
    public function postFeePayment(FeePayment $payment): ?Transaction
    {
        if ($payment->gl_transaction_id)         return null; // already posted
        if ((float) $payment->amount_paid <= 0)  return null;

        $settings       = $this->settings($payment->school_id);
        $cashLedgerId   = $settings['gl_cash_ledger_id']       ?? null;
        $incomeLedgerId = $settings['gl_fee_income_ledger_id'] ?? null;

        if (! $cashLedgerId || ! $incomeLedgerId)       return null; // not configured
        if ($cashLedgerId === $incomeLedgerId)          return null; // misconfigured — same ledger

        $this->assertLedgersExist($payment->school_id, [$cashLedgerId, $incomeLedgerId]);

        return DB::transaction(function () use ($payment, $cashLedgerId, $incomeLedgerId) {
            $tx = $this->createTransaction([
                'school_id'        => $payment->school_id,
                'academic_year_id' => $payment->academic_year_id,
                'date'             => $payment->getRawOriginal('payment_date'),
                'type'             => 'receipt',
                'narration'        => 'Fee receipt — ' . ($payment->receipt_no)
                                    . ($payment->student ? ' (' . $payment->student->name . ')' : ''),
                'reference_no'     => $payment->receipt_no,
                'created_by'       => $payment->collected_by,
            ], [
                ['ledger_id' => $cashLedgerId,   'type' => 'debit',  'amount' => $payment->amount_paid, 'description' => 'Fee collected'],
                ['ledger_id' => $incomeLedgerId, 'type' => 'credit', 'amount' => $payment->amount_paid, 'description' => 'Fee income'],
            ]);

            $payment->updateQuietly(['gl_transaction_id' => $tx->id]);

            return $tx;
        });
    }

    // ── Transport Fee Payment ─────────────────────────────────────────────────

    /**
     * Dr  Cash/Bank ledger                  (gl_cash_ledger_id)
     * Cr  Transport Fee Income ledger       (gl_transport_fee_income_ledger_id)
     *
     * Falls back to the regular Fee Income ledger if no dedicated transport
     * income ledger is configured — that way an unmigrated school still sees
     * its transport collections on the P&L. Idempotent: returns null if the
     * receipt is already posted, has zero amount, or GL is not configured.
     */
    public function postTransportFeePayment(TransportFeePayment $payment): ?Transaction
    {
        if ($payment->gl_transaction_id)         return null; // already posted
        if ((float) $payment->amount_paid <= 0)  return null;

        $settings        = $this->settings($payment->school_id);
        $cashLedgerId    = $settings['gl_cash_ledger_id'] ?? null;
        $incomeLedgerId  = $settings['gl_transport_fee_income_ledger_id']
                        ?? $settings['gl_fee_income_ledger_id']
                        ?? null;

        if (! $cashLedgerId || ! $incomeLedgerId)  return null; // not configured
        if ($cashLedgerId === $incomeLedgerId)     return null; // misconfigured

        $this->assertLedgersExist($payment->school_id, [$cashLedgerId, $incomeLedgerId]);

        $payment->loadMissing('student');
        $studentName = $payment->student
            ? trim(($payment->student->first_name ?? '') . ' ' . ($payment->student->last_name ?? ''))
            : null;

        return DB::transaction(function () use ($payment, $cashLedgerId, $incomeLedgerId, $studentName) {
            $tx = $this->createTransaction([
                'school_id'        => $payment->school_id,
                'academic_year_id' => $payment->academic_year_id,
                'date'             => $payment->getRawOriginal('payment_date'),
                'type'             => 'receipt',
                'narration'        => 'Transport fee receipt — ' . $payment->receipt_no
                                    . ($studentName ? ' (' . $studentName . ')' : ''),
                'reference_no'     => $payment->receipt_no,
                'created_by'       => $payment->collected_by,
            ], [
                ['ledger_id' => $cashLedgerId,   'type' => 'debit',  'amount' => $payment->amount_paid, 'description' => 'Transport fee collected'],
                ['ledger_id' => $incomeLedgerId, 'type' => 'credit', 'amount' => $payment->amount_paid, 'description' => 'Transport fee income'],
            ]);

            $payment->updateQuietly(['gl_transaction_id' => $tx->id]);

            return $tx;
        });
    }

    /**
     * Reverse the GL entry for a transport fee receipt that has been voided
     * (soft-deleted). Creates a balanced reversal transaction (debit/credit
     * lines swapped) and marks the original transaction as void.
     *
     * Safe to call on a payment with no posting — it just returns null.
     */
    public function reverseTransportFeePayment(TransportFeePayment $payment): ?Transaction
    {
        if (! $payment->gl_transaction_id) return null;

        $oldTx = Transaction::with('lines')->find($payment->gl_transaction_id);
        if (! $oldTx || $oldTx->status !== 'posted') return null;

        return DB::transaction(function () use ($payment, $oldTx) {
            $reversalLines = $oldTx->lines->map(fn($l) => [
                'ledger_id'   => $l->ledger_id,
                'type'        => $l->type === 'debit' ? 'credit' : 'debit',
                'amount'      => $l->amount,
                'description' => 'Reversal: ' . $l->description,
            ])->all();

            $reversal = $this->createTransaction([
                'school_id'        => $oldTx->school_id,
                'academic_year_id' => $oldTx->academic_year_id,
                'date'             => now()->toDateString(),
                'type'             => 'journal',
                'narration'        => 'Reversal of ' . $oldTx->transaction_no . ' (void transport receipt)',
                'reversal_of'      => $oldTx->id,
                'created_by'       => auth()->id(),
            ], $reversalLines);

            $oldTx->update(['status' => 'void']);
            $payment->updateQuietly(['gl_transaction_id' => null]);

            return $reversal;
        });
    }

    // ── Hostel Fee Payment ────────────────────────────────────────────────────

    /**
     * Dr  Cash/Bank ledger              (gl_cash_ledger_id)
     * Cr  Hostel Fee Income ledger     (gl_hostel_fee_income_ledger_id)
     *
     * Falls back to the regular Fee Income ledger if no dedicated hostel
     * income ledger is configured. Idempotent: returns null if the receipt
     * is already posted, has zero amount, or GL is not configured.
     */
    public function postHostelFeePayment(HostelFeePayment $payment): ?Transaction
    {
        if ($payment->gl_transaction_id)         return null; // already posted
        if ((float) $payment->amount_paid <= 0)  return null;

        $settings        = $this->settings($payment->school_id);
        $cashLedgerId    = $settings['gl_cash_ledger_id'] ?? null;
        $incomeLedgerId  = $settings['gl_hostel_fee_income_ledger_id']
                        ?? $settings['gl_fee_income_ledger_id']
                        ?? null;

        if (! $cashLedgerId || ! $incomeLedgerId)  return null; // not configured
        if ($cashLedgerId === $incomeLedgerId)     return null; // misconfigured

        $this->assertLedgersExist($payment->school_id, [$cashLedgerId, $incomeLedgerId]);

        $payment->loadMissing('student');
        $studentName = $payment->student
            ? trim(($payment->student->first_name ?? '') . ' ' . ($payment->student->last_name ?? ''))
            : null;

        return DB::transaction(function () use ($payment, $cashLedgerId, $incomeLedgerId, $studentName) {
            $tx = $this->createTransaction([
                'school_id'        => $payment->school_id,
                'academic_year_id' => $payment->academic_year_id,
                'date'             => $payment->getRawOriginal('payment_date'),
                'type'             => 'receipt',
                'narration'        => 'Hostel fee receipt — ' . $payment->receipt_no
                                    . ($studentName ? ' (' . $studentName . ')' : ''),
                'reference_no'     => $payment->receipt_no,
                'created_by'       => $payment->collected_by,
            ], [
                ['ledger_id' => $cashLedgerId,   'type' => 'debit',  'amount' => $payment->amount_paid, 'description' => 'Hostel fee collected'],
                ['ledger_id' => $incomeLedgerId, 'type' => 'credit', 'amount' => $payment->amount_paid, 'description' => 'Hostel fee income'],
            ]);

            $payment->updateQuietly(['gl_transaction_id' => $tx->id]);

            return $tx;
        });
    }

    /**
     * Reverse the GL entry for a hostel fee receipt that has been voided
     * (soft-deleted). Mirrors reverseTransportFeePayment().
     */
    public function reverseHostelFeePayment(HostelFeePayment $payment): ?Transaction
    {
        if (! $payment->gl_transaction_id) return null;

        $oldTx = Transaction::with('lines')->find($payment->gl_transaction_id);
        if (! $oldTx || $oldTx->status !== 'posted') return null;

        return DB::transaction(function () use ($payment, $oldTx) {
            $reversalLines = $oldTx->lines->map(fn($l) => [
                'ledger_id'   => $l->ledger_id,
                'type'        => $l->type === 'debit' ? 'credit' : 'debit',
                'amount'      => $l->amount,
                'description' => 'Reversal: ' . $l->description,
            ])->all();

            $reversal = $this->createTransaction([
                'school_id'        => $oldTx->school_id,
                'academic_year_id' => $oldTx->academic_year_id,
                'date'             => now()->toDateString(),
                'type'             => 'journal',
                'narration'        => 'Reversal of ' . $oldTx->transaction_no . ' (void hostel receipt)',
                'reversal_of'      => $oldTx->id,
                'created_by'       => auth()->id(),
            ], $reversalLines);

            $oldTx->update(['status' => 'void']);
            $payment->updateQuietly(['gl_transaction_id' => null]);

            return $reversal;
        });
    }

    // ── Expense ───────────────────────────────────────────────────────────────

    /**
     * Dr  Expense ledger (category-specific ledger OR gl_expense_ledger_id)
     * Cr  Cash/Bank ledger (gl_cash_ledger_id)
     */
    public function postExpense(Expense $expense): ?Transaction
    {
        if ($expense->gl_transaction_id) return null; // already posted

        $settings     = $this->settings($expense->school_id);
        $cashLedgerId = $settings['gl_cash_ledger_id'] ?? null;

        // Per-category ledger takes priority over the generic expense ledger
        $expense->loadMissing('category');
        $expenseLedgerId = $expense->category?->ledger_id
                         ?? $settings['gl_expense_ledger_id']
                         ?? null;

        if (! $cashLedgerId || ! $expenseLedgerId) return null;

        $this->assertLedgersExist($expense->school_id, [$cashLedgerId, $expenseLedgerId]);

        return DB::transaction(function () use ($expense, $cashLedgerId, $expenseLedgerId) {
            $tx = $this->createTransaction([
                'school_id'        => $expense->school_id,
                'academic_year_id' => $expense->academic_year_id,
                'date'             => $expense->getRawOriginal('expense_date'),
                'type'             => 'payment',
                'narration'        => 'Expense: ' . $expense->title
                                    . ($expense->category ? ' [' . $expense->category->name . ']' : ''),
                'reference_no'     => $expense->transaction_ref,
                'created_by'       => $expense->recorded_by,
            ], [
                ['ledger_id' => $expenseLedgerId, 'type' => 'debit',  'amount' => $expense->amount, 'description' => $expense->title],
                ['ledger_id' => $cashLedgerId,    'type' => 'credit', 'amount' => $expense->amount, 'description' => 'Cash/bank paid'],
            ]);

            $expense->updateQuietly(['gl_transaction_id' => $tx->id]);

            return $tx;
        });
    }

    /**
     * Void the existing GL entry (if any) and repost the expense to the current
     * category ledger mapping. Use this when the category's ledger_id has changed
     * after the initial posting.
     *
     * - Creates a reversal transaction (swapped debit/credit lines, reversal_of = old id)
     * - Marks the old transaction as void
     * - Posts a fresh transaction with the correct ledger
     */
    public function repostExpense(Expense $expense): ?Transaction
    {
        if ($expense->gl_transaction_id) {
            $oldTx = Transaction::with('lines')->find($expense->gl_transaction_id);

            if ($oldTx && $oldTx->status === 'posted') {
                DB::transaction(function () use ($expense, $oldTx) {
                    // Reversal: swap debit ↔ credit on every line
                    $reversalLines = $oldTx->lines->map(fn($l) => [
                        'ledger_id'   => $l->ledger_id,
                        'type'        => $l->type === 'debit' ? 'credit' : 'debit',
                        'amount'      => $l->amount,
                        'description' => 'Reversal: ' . $l->description,
                    ])->all();

                    $reversal = $this->createTransaction([
                        'school_id'        => $oldTx->school_id,
                        'academic_year_id' => $oldTx->academic_year_id,
                        'date'             => now()->toDateString(),
                        'type'             => 'journal',
                        'narration'        => 'Reversal of ' . $oldTx->transaction_no,
                        'reversal_of'      => $oldTx->id,
                        'created_by'       => auth()->id(),
                    ], $reversalLines);

                    $oldTx->update(['status' => 'void']);

                    $expense->updateQuietly(['gl_transaction_id' => null]);
                });
            } elseif ($oldTx && $oldTx->status === 'void') {
                $expense->updateQuietly(['gl_transaction_id' => null]);
            }
        }

        return $this->postExpense($expense->fresh());
    }

    // ── Payroll ───────────────────────────────────────────────────────────────

    /**
     * Dr  Payroll Expense ledger  (gl_payroll_ledger_id)
     * Cr  Cash/Bank ledger        (gl_cash_ledger_id)
     */
    public function postPayroll(Payroll $payroll): ?Transaction
    {
        if ($payroll->gl_transaction_id)          return null; // already posted
        if ((float) $payroll->net_salary <= 0)    return null;

        $settings        = $this->settings($payroll->school_id);
        $cashLedgerId    = $settings['gl_cash_ledger_id']    ?? null;
        $payrollLedgerId = $settings['gl_payroll_ledger_id'] ?? null;

        if (! $cashLedgerId || ! $payrollLedgerId) return null;

        $this->assertLedgersExist($payroll->school_id, [$cashLedgerId, $payrollLedgerId]);

        $academicYearId = DB::table('academic_years')
            ->where('school_id', $payroll->school_id)
            ->where('is_current', true)
            ->value('id');

        $payroll->loadMissing('staff');
        $staffName = $payroll->staff?->name ?? ('Staff #' . $payroll->staff_id);

        return DB::transaction(function () use ($payroll, $cashLedgerId, $payrollLedgerId, $academicYearId, $staffName) {
            $amount = (float) $payroll->net_salary;

            $tx = $this->createTransaction([
                'school_id'        => $payroll->school_id,
                'academic_year_id' => $academicYearId,
                'date'             => $payroll->getRawOriginal('payment_date') ?? now()->toDateString(),
                'type'             => 'payment',
                'narration'        => "Payroll: {$staffName} ({$payroll->month}/{$payroll->year})",
                'reference_no'     => 'PAYROLL-' . $payroll->id,
                'created_by'       => auth()->id(),
            ], [
                ['ledger_id' => $payrollLedgerId, 'type' => 'debit',  'amount' => $amount, 'description' => 'Payroll expense'],
                ['ledger_id' => $cashLedgerId,    'type' => 'credit', 'amount' => $amount, 'description' => 'Cash paid for salary'],
            ]);

            $payroll->updateQuietly(['gl_transaction_id' => $tx->id]);

            return $tx;
        });
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Return school GL settings, auto-creating default ledgers if any mapping is missing.
     * This mirrors ensureDefaultLedgers in GlConfigController so auto-posting never silently
     * fails just because the admin hasn't visited the GL Config page yet.
     */
    private function settings(int $schoolId): array
    {
        $school   = School::find($schoolId);
        $settings = $school?->settings ?? [];

        $needsCash      = empty($settings['gl_cash_ledger_id']);
        $needsIncome    = empty($settings['gl_fee_income_ledger_id']);
        $needsTransport = empty($settings['gl_transport_fee_income_ledger_id']);
        $needsHostel    = empty($settings['gl_hostel_fee_income_ledger_id']);
        $needsExpense   = empty($settings['gl_expense_ledger_id']);

        if (! $needsCash && ! $needsIncome && ! $needsTransport && ! $needsHostel && ! $needsExpense) {
            return $settings;
        }

        $changed = false;

        if ($needsCash) {
            $assetType  = LedgerType::firstOrCreate(['school_id' => $schoolId, 'name' => 'Asset'],   ['nature' => 'debit',  'is_system' => true]);
            $cashLedger = Ledger::firstOrCreate(['school_id' => $schoolId, 'name' => 'Cash / Bank'], ['ledger_type_id' => $assetType->id, 'is_system' => true, 'is_active' => true, 'opening_balance' => 0, 'opening_balance_type' => 'debit']);
            $settings['gl_cash_ledger_id'] = $cashLedger->id;
            $changed = true;
        }

        if ($needsIncome) {
            $incomeType   = LedgerType::firstOrCreate(['school_id' => $schoolId, 'name' => 'Income'],    ['nature' => 'credit', 'is_system' => true]);
            $incomeLedger = Ledger::firstOrCreate(['school_id' => $schoolId, 'name' => 'Fee Income'],   ['ledger_type_id' => $incomeType->id, 'is_system' => true, 'is_active' => true, 'opening_balance' => 0, 'opening_balance_type' => 'credit']);
            $settings['gl_fee_income_ledger_id'] = $incomeLedger->id;
            $changed = true;
        }

        if ($needsTransport) {
            $incomeType  = LedgerType::firstOrCreate(['school_id' => $schoolId, 'name' => 'Income'],            ['nature' => 'credit', 'is_system' => true]);
            $transLedger = Ledger::firstOrCreate(['school_id' => $schoolId, 'name' => 'Transport Fee Income'], ['ledger_type_id' => $incomeType->id, 'is_system' => true, 'is_active' => true, 'opening_balance' => 0, 'opening_balance_type' => 'credit']);
            $settings['gl_transport_fee_income_ledger_id'] = $transLedger->id;
            $changed = true;
        }

        if ($needsHostel) {
            $incomeType   = LedgerType::firstOrCreate(['school_id' => $schoolId, 'name' => 'Income'],         ['nature' => 'credit', 'is_system' => true]);
            $hostelLedger = Ledger::firstOrCreate(['school_id' => $schoolId, 'name' => 'Hostel Fee Income'], ['ledger_type_id' => $incomeType->id, 'is_system' => true, 'is_active' => true, 'opening_balance' => 0, 'opening_balance_type' => 'credit']);
            $settings['gl_hostel_fee_income_ledger_id'] = $hostelLedger->id;
            $changed = true;
        }

        if ($needsExpense) {
            $expenseType   = LedgerType::firstOrCreate(['school_id' => $schoolId, 'name' => 'Expense'],        ['nature' => 'debit',  'is_system' => true]);
            $expenseLedger = Ledger::firstOrCreate(['school_id' => $schoolId, 'name' => 'General Expenses'], ['ledger_type_id' => $expenseType->id, 'is_system' => true, 'is_active' => true, 'opening_balance' => 0, 'opening_balance_type' => 'debit']);
            $settings['gl_expense_ledger_id'] = $expenseLedger->id;
            $changed = true;
        }

        if ($changed && $school) {
            $school->update(['settings' => $settings]);
            Log::info("GL auto-configured for school #{$schoolId}");
        }

        return $settings;
    }

    private function assertLedgersExist(int $schoolId, array $ids): void
    {
        $unique = array_unique($ids);
        $found  = Ledger::where('school_id', $schoolId)->whereIn('id', $unique)->count();
        if ($found !== count($unique)) {
            throw new \RuntimeException('One or more GL ledgers are not valid for this school.');
        }
    }

    private function createTransaction(array $header, array $lines): Transaction
    {
        $tx = Transaction::create(array_merge($header, [
            'transaction_no' => Transaction::generateNo($header['school_id']),
            'status'         => 'posted',
        ]));

        foreach ($lines as $line) {
            TransactionLine::create(array_merge($line, ['transaction_id' => $tx->id]));
        }

        return $tx;
    }
}
