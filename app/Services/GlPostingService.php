<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FeePayment;
use App\Models\Ledger;
use App\Models\Payroll;
use App\Models\School;
use App\Models\Transaction;
use App\Models\TransactionLine;
use Illuminate\Support\Facades\DB;

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
                'date'             => $payment->payment_date->toDateString(),
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
                'date'             => $expense->expense_date->toDateString(),
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
                'date'             => ($payroll->payment_date ?? now())->toDateString(),
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

    private function settings(int $schoolId): array
    {
        return School::find($schoolId)?->settings ?? [];
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
