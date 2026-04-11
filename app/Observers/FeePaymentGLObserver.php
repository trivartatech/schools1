<?php

namespace App\Observers;

use App\Models\FeePayment;
use App\Services\GlPostingService;

/**
 * Auto-posts a GL receipt entry when a fee payment is created.
 * All logic lives in GlPostingService (idempotent, school-scoped).
 *
 * Entry: Dr Cash/Bank → Cr Fee Income
 */
class FeePaymentGLObserver
{
    public function created(FeePayment $payment): void
    {
        try {
            app(GlPostingService::class)->postFeePayment($payment);
        } catch (\Throwable $e) {
            // GL posting failure should not block fee collection.
            // The payment can be synced later via "Sync All to GL".
            \Illuminate\Support\Facades\Log::warning('GL auto-post failed for FeePayment #' . $payment->id . ': ' . $e->getMessage());
        }
    }
}
