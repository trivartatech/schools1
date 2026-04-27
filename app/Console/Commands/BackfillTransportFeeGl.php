<?php

namespace App\Console\Commands;

use App\Models\TransportFeePayment;
use App\Services\GlPostingService;
use Illuminate\Console\Command;

/**
 * Posts historical transport-fee receipts to the General Ledger so the
 * trial balance, P&L and balance sheet reflect transport revenue collected
 * before the GL auto-posting observer was added.
 *
 * Run AFTER configuring `gl_transport_fee_income_ledger_id` for the school
 * (or accept the auto-fallback to Fee Income).
 */
class BackfillTransportFeeGl extends Command
{
    protected $signature   = 'transport-fees:backfill-gl
                              {--school= : Limit to a specific school_id}
                              {--dry-run : Print what would be posted without writing}';
    protected $description = 'Backfill GL entries for historical transport fee receipts that have no gl_transaction_id';

    public function handle(GlPostingService $service): int
    {
        $schoolId = $this->option('school');
        $dryRun   = (bool) $this->option('dry-run');

        $query = TransportFeePayment::whereNull('gl_transaction_id')
            ->where('amount_paid', '>', 0);

        if ($schoolId) {
            $query->where('school_id', (int) $schoolId);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nothing to backfill — every transport receipt is already posted (or has zero amount).');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$total} transport receipt(s) without GL postings.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $posted   = 0;
        $skipped  = 0;
        $failed   = 0;

        $query->orderBy('id')->chunkById(500, function ($payments) use ($service, $dryRun, &$posted, &$skipped, &$failed, $bar) {
            foreach ($payments as $payment) {
                try {
                    if ($dryRun) {
                        $this->newLine();
                        $this->line(sprintf('  - Would post #%d  receipt=%s  school=%d  amount=%.2f',
                            $payment->id, $payment->receipt_no, $payment->school_id, (float) $payment->amount_paid));
                        $posted++;
                    } else {
                        $tx = $service->postTransportFeePayment($payment);
                        if ($tx) {
                            $posted++;
                        } else {
                            // Skipped — GL not configured for that school, or zero amount
                            $skipped++;
                        }
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("  ✗ #{$payment->id}: " . $e->getMessage());
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Posted:  {$posted}");
        $this->info("Skipped: {$skipped}  (GL not configured or already posted)");
        if ($failed > 0) {
            $this->warn("Failed:  {$failed}  (see errors above)");
        }
        if ($dryRun) {
            $this->comment('Dry run — no GL entries were written. Re-run without --dry-run to post.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
