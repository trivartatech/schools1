<?php

namespace App\Observers;

use App\Models\Payroll;
use App\Services\GlPostingService;

/**
 * Auto-posts a GL payment entry when a payroll record is marked as paid.
 * Fixes the previous total_amount bug (column does not exist on transactions).
 *
 * Entry: Dr Payroll Expense → Cr Cash/Bank
 */
class PayrollGLObserver
{
    public function updated(Payroll $payroll): void
    {
        if (! $payroll->wasChanged('status') || $payroll->status !== 'paid') {
            return;
        }

        try {
            app(GlPostingService::class)->postPayroll($payroll);
        } catch (\Throwable $e) {
            // GL posting failure should not block marking payroll as paid.
            // The payroll can be synced later via "Post to GL".
            \Illuminate\Support\Facades\Log::warning(
                'GL auto-post failed for Payroll #' . $payroll->id . ': ' . $e->getMessage()
            );
        }
    }
}
