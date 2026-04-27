<?php

namespace App\Observers;

use App\Models\TransportFeePayment;
use App\Services\GlPostingService;

/**
 * Auto-posts a GL receipt entry when a transport fee receipt is created,
 * and creates a balanced reversal entry when one is voided (soft-deleted).
 * All logic lives in GlPostingService (idempotent, school-scoped).
 *
 * Entry: Dr Cash/Bank → Cr Transport Fee Income
 */
class TransportFeePaymentGLObserver
{
    public function created(TransportFeePayment $payment): void
    {
        try {
            app(GlPostingService::class)->postTransportFeePayment($payment);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'GL auto-post failed for TransportFeePayment #' . $payment->id . ': ' . $e->getMessage()
            );
        }
    }

    public function deleted(TransportFeePayment $payment): void
    {
        try {
            app(GlPostingService::class)->reverseTransportFeePayment($payment);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'GL reversal failed for TransportFeePayment #' . $payment->id . ': ' . $e->getMessage()
            );
        }
    }
}
