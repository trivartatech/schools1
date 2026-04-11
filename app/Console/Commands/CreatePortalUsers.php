<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreatePortalUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'portal:create-users {--school= : Optional school ID filter}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create portal user accounts for existing students and parents';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $schoolId = $this->option('school');

        // 1. Process Parents
        $parentQuery = StudentParent::whereNull('user_id');
        if ($schoolId) {
            $parentQuery->where('school_id', $schoolId);
        }

        $parents = $parentQuery->get();
        $this->info("Found {$parents->count()} parents without user accounts.");

        foreach ($parents as $parent) {
            $existingUser = User::where('username', $parent->primary_phone)
                ->orWhere('phone', $parent->primary_phone)
                ->first();

            if ($existingUser) {
                $parent->update(['user_id' => $existingUser->id]);
                if (!$existingUser->hasRole('parent')) {
                    $existingUser->assignRole('parent');
                }
                continue;
            }

            $user = User::create([
                'school_id' => $parent->school_id,
                'name'      => $parent->father_name ?: ($parent->guardian_name ?: 'Parent'),
                'username'  => $parent->primary_phone,
                'phone'     => $parent->primary_phone,
                'password'  => Hash::make('parent123'), // Default
                'user_type' => 'parent',
                'is_active' => true,
            ]);

            $user->assignRole('parent');
            $parent->update(['user_id' => $user->id]);
        }

        $this->info("Parent accounts processed.");

        // 2. Process Students
        $studentQuery = Student::whereNull('user_id');
        if ($schoolId) {
            $studentQuery->where('school_id', $schoolId);
        }

        $students = $studentQuery->get();
        $this->info("Found {$students->count()} students without user accounts.");

        foreach ($students as $student) {
            $existingUser = User::where('username', $student->admission_no)->first();

            if ($existingUser) {
                $student->update(['user_id' => $existingUser->id]);
                if (!$existingUser->hasRole('student')) {
                    $existingUser->assignRole('student');
                }
                continue;
            }

            $dobPassword = $student->dob ? str_replace('-', '', $student->dob) : 'student123';

            $user = User::create([
                'school_id' => $student->school_id,
                'name'      => $student->first_name . ($student->last_name ? ' ' . $student->last_name : ''),
                'username'  => $student->admission_no,
                'password'  => Hash::make($dobPassword),
                'user_type' => 'student',
                'is_active' => true,
            ]);

            $user->assignRole('student');
            $student->update(['user_id' => $user->id]);
        }

        $this->info("Student accounts processed successfully.");
    }
}
