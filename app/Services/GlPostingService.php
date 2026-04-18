<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FeePayment;
use App\Models\Ledger;
use App\Models\LedgerType;
use App\Models\Payroll;
use App\Models\School;
use App\Models\Transaction;
use App\Models\TransactionLine;
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

    /**
     * Return school GL settings, auto-creating default ledgers if any mapping is missing.
     * This mirrors ensureDefaultLedgers in GlConfigController so auto-posting never silently
     * fails just because the admin hasn't visited the GL Config page yet.
     */
    private function settings(int $schoolId): array
    {
        $school   = School::find($schoolId);
        $settings = $school?->settings ?? [];

        $needsCash    = empty($settings['gl_cash_ledger_id']);
        $needsIncome  = empty($settings['gl_fee_income_ledger_id']);
        $needsExpense = empty($settings['gl_expense_ledger_id']);

        if (! $needsCash && ! $needsIncome && ! $needsExpense) {
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
