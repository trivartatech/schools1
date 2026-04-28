<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled Tasks ───────────────────────────────────────────────────────────
//
// Run the Laravel scheduler with:  php artisan schedule:run  (via cron every minute)
// Cron entry:  * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
//
// ─────────────────────────────────────────────────────────────────────────────

// Process queued/scheduled announcements — runs every minute for real-time delivery
Schedule::command('announcements:process')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Clean up expired voice call cache files — hourly, low priority
Schedule::command('app:cleanup-voice-cache')
    ->hourly()
    ->withoutOverlapping();

// Prune stale Sanctum tokens older than 30 days (Laravel built-in)
Schedule::command('sanctum:prune-expired', ['--hours' => 720])
    ->daily();

// Prune old activity log records older than 1 year to keep the table lean
Schedule::command('activitylog:clean', ['--days' => 365])
    ->weekly()
    ->sundays()
    ->at('02:00');

// Clear expired password reset tokens
Schedule::command('auth:clear-resets')
    ->everyFifteenMinutes();

// Delete soft-deleted records older than 90 days (model pruning)
// Models must use the Prunable trait to be included
Schedule::command('model:prune')
    ->daily()
    ->at('03:00');

// Auto-fill 'holiday' attendance rows for every active student and staff on
// declared school holidays. Idempotent — safe to re-run. Runs early so the
// rows exist before anyone opens the dashboard / mark page.
Schedule::command('attendance:fill-holidays')
    ->dailyAt('00:30')
    ->withoutOverlapping();

