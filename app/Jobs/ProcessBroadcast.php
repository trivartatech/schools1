<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Services\BroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $announcement;
    public $timeout = 3600; // 1 hour for large school broadcasts
    public $tries = 1; // Do not retry to avoid double-sending calls/SMS

    /**
     * Create a new job instance.
     */
    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    /**
     * Execute the job.
     */
    public function handle(BroadcastService $broadcastService): void
    {
        // Mark as being broadcasted immediately to prevent re-dispatch risk even if job is killed/timeout
        $this->announcement->update(['is_broadcasted' => true]);

        try {
            Log::info("ProcessBroadcast Job started for Announcement ID {$this->announcement->id}");
            $broadcastService->processIndividualMessages($this->announcement);
            
            // Success: clear any previous error info
            $this->announcement->update([
                'broadcast_error' => null,
                'failed_at'       => null
            ]);

            Log::info("ProcessBroadcast Job finished for Announcement ID {$this->announcement->id}");
        } catch (\Throwable $e) {
            Log::error("ProcessBroadcast Job runtime error for ID {$this->announcement->id}: " . $e->getMessage());
            throw $e; // Rethrow to trigger failed() logic
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical("❌ ProcessBroadcast Job FAILED for Announcement ID {$this->announcement->id}: " . $exception->getMessage());
        
        // Update announcement with structured failure info
        $this->announcement->update([
            'is_broadcasted'  => true, 
            'broadcast_error' => [
                'message' => $exception->getMessage(),
                'trace'   => substr($exception->getTraceAsString(), 0, 500)
            ],
            'failed_at'       => now(),
        ]);

        // ACTIVE ALERT: Notify the school admin via DB notification
        try {
            $sender = $this->announcement->sender;
            if ($sender) {
                $sender->notify(new \App\Notifications\BroadcastFailed(
                    $this->announcement->title,
                    $exception->getMessage()
                ));
            }
        } catch (\Exception $e) {
            Log::error("Failed to send failure notification: " . $e->getMessage());
        }
    }
}
