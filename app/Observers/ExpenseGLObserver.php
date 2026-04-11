<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\GlPostingService;

/**
 * Auto-posts a GL payment entry when an expense is created.
 * Uses per-category ledger if mapped, else falls back to gl_expense_ledger_id.
 *
 * Entry: Dr Expense ledger → Cr Cash/Bank
 */
class ExpenseGLObserver
{
    public function created(Expense $expense): void
    {
        app(GlPostingService::class)->postExpense($expense);
    }
}
