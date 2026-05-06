<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessScheduledAnnouncements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcements:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and broadcast scheduled announcements';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\BroadcastService $broadcastService)
    {
        $this->info('Starting scheduled announcements processing.');

        $pending = \App\Models\Announcement::where('is_broadcasted', false)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending scheduled announcements found.');
            return;
        }

        foreach ($pending as $announcement) {
            $this->info("Broadcasting announcement: {$announcement->title} (ID: {$announcement->id})");
            try {
                $count = $broadcastService->broadcast($announcement);
                $announcement->update(['is_broadcasted' => true]);
                $this->info("Broadcast successful for {$count} users.");
            } catch (\Exception $e) {
                $this->error("Failed to broadcast announcement ID {$announcement->id}: " . $e->getMessage());
                // Leave is_broadcasted = false so the next run retries, but log to avoid silent infinite loops
                \Illuminate\Support\Facades\Log::error('announcements:process failed', [
                    'announcement_id' => $announcement->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        $this->info('Finished processing scheduled announcements.');
    }
}
