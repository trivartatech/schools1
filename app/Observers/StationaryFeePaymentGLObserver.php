<?php

namespace App\Observers;

use App\Models\StationaryFeePayment;
use App\Services\GlPostingService;

/**
 * Auto-posts a GL receipt entry when a stationary fee receipt is created,
 * and creates a balanced reversal entry when one is voided (soft-deleted).
 * All logic lives in GlPostingService (idempotent, school-scoped).
 *
 * Entry: Dr Cash/Bank → Cr Stationary Fee Income
 */
class StationaryFeePaymentGLObserver
{
    public function created(StationaryFeePayment $payment): void
    {
        try {
            app(GlPostingService::class)->postStationaryFeePayment($payment);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'GL auto-post failed for StationaryFeePayment #' . $payment->id . ': ' . $e->getMessage()
            );
        }
    }

    public function deleted(StationaryFeePayment $payment): void
    {
        try {
            app(GlPostingService::class)->reverseStationaryFeePayment($payment);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'GL reversal failed for StationaryFeePayment #' . $payment->id . ': ' . $e->getMessage()
            );
        }
    }
}
