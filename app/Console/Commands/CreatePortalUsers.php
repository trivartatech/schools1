<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Bulk-create portal user accounts for existing parents and students that
 * don't have a User row yet.
 *
 * Optimised for thousands of rows:
 *   - Pre-hashes the default password ONCE (one bcrypt call, not N).
 *   - Chunks the queries to keep memory flat.
 *   - Per-row try/catch so a single bad row never aborts the batch.
 */
class CreatePortalUsers extends Command
{
    protected $signature   = 'portal:create-users
                              {--school= : Optional school ID filter}
                              {--type=all : parent | student | all}';

    protected $description = 'Create portal user accounts for existing students and parents';

    public function handle(): int
    {
        $schoolId = $this->option('school');
        $type     = $this->option('type') ?: 'all';

        if (! in_array($type, ['parent', 'student', 'all'], true)) {
            $this->error("Invalid --type value: {$type}. Must be parent | student | all.");
            return self::FAILURE;
        }

        // One bcrypt call reused for every new user (plaintext is a known
        // default; users are expected to change on first login).
        $passwordPlain = 'password';
        $passwordHash  = Hash::make($passwordPlain);

        $createdParents  = 0;
        $createdStudents = 0;
        $failed          = 0;

        if (in_array($type, ['parent', 'all'], true)) {
            $createdParents = $this->processParents($schoolId, $passwordHash, $failed);
        }

        if (in_array($type, ['student', 'all'], true)) {
            $createdStudents = $this->processStudents($schoolId, $passwordHash, $failed);
        }

        $this->newLine();
        $this->info("Created parents:  {$createdParents}");
        $this->info("Created students: {$createdStudents}");
        if ($failed > 0) {
            $this->warn("Failed:          {$failed}  (see Laravel log)");
        }
        $this->info("Default password: {$passwordPlain}");

        return self::SUCCESS;
    }

    private function processParents($schoolId, string $passwordHash, int &$failed): int
    {
        $query = StudentParent::whereNull('user_id')->whereNotNull('primary_phone');
        if ($schoolId) $query->where('school_id', (int) $schoolId);

        $total = (clone $query)->count();
        $this->info("Found {$total} parents without user accounts.");
        if ($total === 0) return 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $created = 0;

        $query->orderBy('id')->chunkById(200, function ($parents) use ($passwordHash, &$created, &$failed, $bar) {
            foreach ($parents as $parent) {
                try {
                    $existingUser = User::where('username', $parent->primary_phone)
                        ->orWhere('phone', $parent->primary_phone)
                        ->first();

                    if ($existingUser) {
                        $parent->update(['user_id' => $existingUser->id]);
                        if (! $existingUser->hasRole('parent')) $existingUser->assignRole('parent');
                    } else {
                        $user = User::create([
                            'school_id' => $parent->school_id,
                            'name'      => $parent->father_name ?: ($parent->guardian_name ?: 'Parent'),
                            'username'  => $parent->primary_phone,
                            'phone'     => $parent->primary_phone,
                            'password'  => $passwordHash,
                            'user_type' => 'parent',
                            'is_active' => true,
                        ]);
                        $user->assignRole('parent');
                        $parent->update(['user_id' => $user->id]);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('portal:create-users parent failure', [
                        'parent_id' => $parent->id,
                        'phone'     => $parent->primary_phone,
                        'error'     => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        return $created;
    }

    private function processStudents($schoolId, string $passwordHash, int &$failed): int
    {
        $query = Student::whereNull('user_id')->whereNotNull('admission_no');
        if ($schoolId) $query->where('school_id', (int) $schoolId);

        $total = (clone $query)->count();
        $this->info("Found {$total} students without user accounts.");
        if ($total === 0) return 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $created = 0;

        $query->orderBy('id')->chunkById(200, function ($students) use ($passwordHash, &$created, &$failed, $bar) {
            foreach ($students as $student) {
                try {
                    $existingUser = User::where('username', $student->admission_no)->first();

                    if ($existingUser) {
                        $student->update(['user_id' => $existingUser->id]);
                        if (! $existingUser->hasRole('student')) $existingUser->assignRole('student');
                    } else {
                        $user = User::create([
                            'school_id' => $student->school_id,
                            'name'      => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                            'username'  => $student->admission_no,
                            'password'  => $passwordHash,
                            'user_type' => 'student',
                            'is_active' => true,
                        ]);
                        $user->assignRole('student');
                        $student->update(['user_id' => $user->id]);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('portal:create-users student failure', [
                        'student_id'   => $student->id,
                        'admission_no' => $student->admission_no,
                        'error'        => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        return $created;
    }
}
