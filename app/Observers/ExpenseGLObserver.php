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
        try {
            app(GlPostingService::class)->postExpense($expense);
        } catch (\Throwable $e) {
            // GL posting failure should not block expense recording.
            // The expense can be synced later via "Post All to GL".
            \Illuminate\Support\Facades\Log::warning(
                'GL auto-post failed for Expense #' . $expense->id . ': ' . $e->getMessage()
            );
        }
    }
}
