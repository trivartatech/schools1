<?php

namespace App\Observers;

use App\Models\StationaryReturn;
use App\Services\GlPostingService;

/**
 * Auto-posts a reverse GL entry when a stationary return is recorded
 * with a real money refund (refund_mode in {cash, cheque}).
 *
 * For refund_mode = 'adjust' or 'none' no GL entry is needed — those are
 * handled at the allocation level (amount_paid is decremented logically).
 *
 * Soft-deleting (voiding) a return reverses the refund GL entry if any.
 *
 * Entry: Dr Stationary Fee Income → Cr Cash/Bank
 */
class StationaryReturnGLObserver
{
    public function created(StationaryReturn $return): void
    {
        if (! in_array($return->refund_mode, ['cash', 'cheque'], true)) return;
        if ((float) $return->refund_amount <= 0) return;

        try {
            app(GlPostingService::class)->postStationaryRefund($return);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'GL refund-post failed for StationaryReturn #' . $return->id . ': ' . $e->getMessage()
            );
        }
    }

    public function deleted(StationaryReturn $return): void
    {
        if (! $return->gl_transaction_id) return;

        try {
            app(GlPostingService::class)->reverseStationaryRefund($return);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'GL refund-reversal failed for StationaryReturn #' . $return->id . ': ' . $e->getMessage()
            );
        }
    }
}
