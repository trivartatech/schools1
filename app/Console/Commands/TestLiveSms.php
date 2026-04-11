<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\School;
use App\Models\Student;
use App\Services\NotificationService;
use App\Models\CommunicationLog;

class TestLiveSms extends Command
{
    protected $signature = 'test:live-sms';
    protected $description = 'Test live SMS delivery via MSG91 API';

    public function handle()
    {
        $this->info("--- STARTING LIVE SMS TEST ---");

        $school = School::first();
        if (!$school) {
            $this->error("No school found.");
            return 1;
        }

        $originalSettings = $school->settings;

        // Force a key for testing (User must supply real key later, we use a placeholder to ensure HTTP attempt)
        $this->info("1. Setting temporary live test key...");
        $settings = $school->settings;
        $settings['sms']['api_key'] = '1234567890123456789012'; // MSG91 keys are typically 22-24 alphanumeric chars
        $school->update(['settings' => $settings]);


        $this->info("2. Triggering NotificationService...");
        $svc = new NotificationService($school->fresh());
        
        $student = Student::whereHas('studentParent', function($q) {
            $q->whereNotNull('primary_phone');
        })
        ->whereHas('currentAcademicHistory')
        ->with('studentParent', 'currentAcademicHistory.courseClass', 'currentAcademicHistory.section')
        ->first();

        if (!$student) {
            $this->error("No valid test student found.");
            return 1;
        }

        $this->info("   Testing with Student ID: {$student->id}, Phone: {$student->studentParent->primary_phone}");

        // Trigger SMS logic
        $svc->notifyAttendance($student, 'absent');

        // Verify Log
        $this->info("3. Verifying MSG91 API Response...");
        $log = CommunicationLog::where('school_id', $school->id)
                                ->where('to', $student->studentParent->primary_phone)
                                ->latest()
                                ->first();

        if ($log) {
            $this->info("   Status: " . $log->status);
            $this->info("   Provider Response: " . json_encode($log->provider_response));
            
            if ($log->status === 'sent') {
                $this->info("   [SUCCESS] MSG91 accepted the request!");
            } else {
                $this->error("   [FAILURE] MSG91 rejected the request.");
            }
        } else {
            $this->error("   [ERROR] No log created. Check NotificationService execution.");
        }

        // Cleanup
        $school->update(['settings' => $originalSettings]);
        $this->info("--- TEST COMPLETE ---");

        return 0;
    }
}
