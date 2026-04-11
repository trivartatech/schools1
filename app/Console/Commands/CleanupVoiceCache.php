<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupVoiceCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-voice-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old voice cache files (WAV/MP3) older than 2 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (!$disk->exists('voice_cache')) {
            return;
        }

        $files = $disk->files('voice_cache');
        $now = time();
        $count = 0;

        foreach ($files as $file) {
            $lastModified = $disk->lastModified($file);
            // Delete if older than 2 hours (7200 seconds)
            if (($now - $lastModified) > 7200) {
                $disk->delete($file);
                $count++;
            }
        }

        $this->info("Cleaned up {$count} voice cache files.");
    }
}
