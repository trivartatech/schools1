<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\DailyReportSetting;
use App\Models\School;
use App\Services\DailyReportBroadcaster;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyMasterReport extends Command
{
    protected $signature = 'report:daily-master
        {--school= : Specific school ID (default: all schools whose configured time matches now)}
        {--date= : YYYY-MM-DD (default: today)}
        {--mode=auto : auto | daily | weekly}
        {--all : Run for all schools regardless of configured time}';

    protected $description = 'Build and broadcast the Daily Master Report (PDF + WhatsApp + SMS) to admin numbers';

    public function handle(DailyReportBroadcaster $broadcaster): int
    {
        $schoolId = $this->option('school');
        $date     = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $mode     = $this->option('mode');
        $runAll   = (bool) $this->option('all');

        $now = now();

        // Resolve schools
        if ($schoolId) {
            $schools = School::where('id', $schoolId)->get();
        } else {
            $query = DailyReportSetting::where('auto_send_enabled', true);
            if (!$runAll) {
                // Match HH:MM of now (allow ±0 minute precision so cron at every minute hits exactly)
                $query->whereTime('auto_send_time', $now->format('H:i:00'));
            }
            $schoolIds = $query->pluck('school_id');
            $schools = School::whereIn('id', $schoolIds)->get();
        }

        if ($schools->isEmpty()) {
            $this->info('No schools to process.');
            return self::SUCCESS;
        }

        $totalWa = 0;
        $totalSms = 0;
        $totalFailed = 0;

        foreach ($schools as $school) {
            $settings = DailyReportSetting::forSchool($school->id);

            // Resolve mode
            $effectiveMode = $mode;
            if ($effectiveMode === 'auto') {
                $effectiveMode = ($date->isSunday() && $settings->weekly_digest_enabled) ? 'weekly' : 'daily';
            }

            // Resolve academic year — pick this school's currently active one
            $academicYear = AcademicYear::where('school_id', $school->id)
                ->current()
                ->first();
            $academicYearId = $academicYear?->id;

            $this->info("→ School #{$school->id} ({$school->name}) — mode={$effectiveMode}, date={$date->toDateString()}");

            try {
                $result = $broadcaster->broadcast($school->id, $date, $effectiveMode, $academicYearId);
                $this->info("  ✓ contacts={$result['contacts']} whatsapp={$result['whatsapp']} sms={$result['sms']} failed={$result['failed']}");

                $totalWa     += $result['whatsapp'];
                $totalSms    += $result['sms'];
                $totalFailed += $result['failed'];
            } catch (\Throwable $e) {
                $this->error("  ✗ Broadcast failed: " . $e->getMessage());
                \Illuminate\Support\Facades\Log::error('[DailyReport] Broadcast failed for school ' . $school->id, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Done. Total: WhatsApp={$totalWa}, SMS={$totalSms}, failed={$totalFailed}");
        return self::SUCCESS;
    }
}
