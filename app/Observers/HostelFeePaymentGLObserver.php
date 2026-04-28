<?php

namespace App\Observers;

use App\Models\HostelFeePayment;
use App\Services\GlPostingService;

/**
 * Auto-posts a GL receipt entry when a hostel fee receipt is created,
 * and creates a balanced reversal entry when one is voided (soft-deleted).
 * All logic lives in GlPostingService (idempotent, school-scoped).
 *
 * Entry: Dr Cash/Bank → Cr Hostel Fee Income
 */
class HostelFeePaymentGLObserver
{
    public function created(HostelFeePayment $payment): void
    {
        try {
            app(GlPostingService::class)->postHostelFeePayment($payment);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'GL auto-post failed for HostelFeePayment #' . $payment->id . ': ' . $e->getMessage()
            );
        }
    }

    public function deleted(HostelFeePayment $payment): void
    {
        try {
            app(GlPostingService::class)->reverseHostelFeePayment($payment);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'GL reversal failed for HostelFeePayment #' . $payment->id . ': ' . $e->getMessage()
            );
        }
    }
}
