<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\School;
use App\Models\Student;
use App\Services\NotificationService;
use App\Models\CommunicationLog;

class VerifyApiConnection extends Command
{
    protected $signature = 'verify:api';
    protected $description = 'Verifies API key connection and NotificationService logic';

    public function handle()
    {
        $this->info("--- STARTING API & CONNECTION TEST ---");

        $school = School::first();
        if (!$school) {
            $this->error("No school found.");
            return 1;
        }

        $originalSettings = $school->settings;

        // 1. Force a key
        $this->info("1. Testing Saved Logic...");
        $settings = $school->settings;
        $settings['sms']['api_key'] = 'ARTISAN_TEST_KEY_123';
        $school->update(['settings' => $settings]);

        $savedKey = $school->fresh()->settings['sms']['api_key'] ?? 'MISSING';
        if ($savedKey === 'ARTISAN_TEST_KEY_123') {
            $this->info("   [SUCCESS] API key saved successfully.");
        } else {
            $this->error("   [FAILURE] API key not saved.");
        }

        // 2. Trigger Notification
        $this->info("2. Testing NotificationService Connection...");
        $svc = new NotificationService($school->fresh());
        
        // Find a student with a valid parent, phone, AND current academic history to pass template data compilation
        $student = Student::whereHas('studentParent', function($q) {
            $q->whereNotNull('primary_phone');
        })
        ->whereHas('currentAcademicHistory')
        ->with('studentParent', 'currentAcademicHistory.courseClass', 'currentAcademicHistory.section')
        ->first();

        if (!$student) {
            $this->error("   [ERROR] No valid test student (parent phone + academic history) found.");
            return 1;
        }

        $this->info("   Testing with Student ID: {$student->id}, Parent Primary Mobile: {$student->studentParent->primary_phone}");

        // Trigger SMS logic
        $svc->notifyAttendance($student, 'present');

        // 3. Verify Log
        $this->info("3. Verifying Communication Log...");
        $log = CommunicationLog::where('school_id', $school->id)
                                ->where('to', $student->studentParent->primary_phone)
                                ->latest()
                                ->first();

        if ($log && strpos($log->message, 'present') !== false) {
            $this->info("   [SUCCESS] Notification logic triggered!");
            $this->info("   LOG TO: " . $log->to);
            $this->info("   LOG MSG: " . $log->message);
        } else {
            $this->error("   [FAILURE] No matching communication log found.");
            if ($log) {
                $this->warn("   Found a log, but it didn't match perfectly. TO: {$log->to}, MSG: {$log->message}");
            }
        }

        // 4. Cleanup
        $school->update(['settings' => $originalSettings]);
        $this->info("--- TEST COMPLETE ---");

        return 0;
    }
}
