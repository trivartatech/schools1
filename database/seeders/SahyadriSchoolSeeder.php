<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Organization;
use App\Models\School;

/**
 * SahyadriSchoolSeeder
 * -----------------------------------------------------------------------------
 * Bootstraps a single tenant for Sahyadri School
 * (erp.sahyadrischool.net.in).
 *
 * Creates:
 *   - Organization (trust-level record)
 *   - School with default feature flags enabled
 *   - Current academic year (Apr - Mar)
 *   - Super admin, school admin, and principal users
 *
 * Run: php artisan db:seed --class=SahyadriSchoolSeeder
 */
class SahyadriSchoolSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'sahyadri-school-trust'],
            [
                'name'    => 'Sahyadri School Trust',
                'email'   => 'info@sahyadrischool.net.in',
                'phone'   => '',
                'address' => '',
                'website' => 'https://sahyadrischool.net.in',
            ]
        );

        $school = School::firstOrCreate(
            ['slug' => 'sahyadri-school'],
            [
                'organization_id' => $org->id,
                'name'            => 'Sahyadri School',
                'code'            => 'SAH001',
                'board'           => 'CBSE',
                'email'           => 'admin@sahyadrischool.net.in',
                'phone'           => '',
                'address'         => '',
                'city'            => '',
                'state'           => '',
                'pincode'         => '',
                'website'         => 'https://sahyadrischool.net.in',
                'principal_name'  => 'Principal',
                'timezone'        => 'Asia/Kolkata',
                'currency'        => 'INR',
                'language'        => 'en',
                'status'          => 'active',
                'features'        => [
                    'attendance'     => true,
                    'fee'            => true,
                    'exam'           => true,
                    'communication'  => true,
                    'transport'      => true,
                    'hostel'         => true,
                    'chat'           => true,
                    'social_buzz'    => true,
                    'classes'        => true,
                    'staff'          => true,
                    'payroll'        => true,
                    'schedule'       => true,
                    'academic'       => true,
                    'front_office'   => true,
                    'settings'       => true,
                    'expense'        => true,
                    'reports'        => true,
                    'students'       => true,
                ],
                'settings'        => [
                    'footer_credit' => '© ' . date('Y') . ' Sahyadri School. All rights reserved.',
                ],
            ]
        );

        $year = date('Y');
        $school->academicYears()->firstOrCreate(
            ['name' => $year . '-' . ($year + 1)],
            [
                'start_date' => $year . '-04-01',
                'end_date'   => ($year + 1) . '-03-31',
                'is_current' => true,
                'status'     => 'active',
            ]
        );

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@sahyadrischool.net.in'],
            [
                'name'      => 'Super Administrator',
                'password'  => Hash::make('ChangeMe@2026'),
                'user_type' => 'super_admin',
                'is_active' => true,
            ]
        );
        $this->safeAssignRole($superAdmin, 'super_admin');

        $schoolAdmin = User::firstOrCreate(
            ['email' => 'admin@sahyadrischool.net.in'],
            [
                'school_id'       => $school->id,
                'organization_id' => $org->id,
                'name'      => 'School Administrator',
                'password'  => Hash::make('ChangeMe@2026'),
                'user_type' => 'school_admin',
                'is_active' => true,
            ]
        );
        $this->safeAssignRole($schoolAdmin, 'school_admin');
        $this->safeAssignRole($schoolAdmin, 'admin');

        $principal = User::firstOrCreate(
            ['email' => 'principal@sahyadrischool.net.in'],
            [
                'school_id'       => $school->id,
                'organization_id' => $org->id,
                'name'      => 'Principal',
                'password'  => Hash::make('ChangeMe@2026'),
                'user_type' => 'principal',
                'is_active' => true,
            ]
        );
        $this->safeAssignRole($principal, 'principal');

        $this->command->info('Sahyadri School seeded.');
        $this->command->warn('Default passwords are "ChangeMe@2026" — change them after first login.');
    }

    private function safeAssignRole(User $user, string $role): void
    {
        try {
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }
        } catch (\Throwable $e) {
            // role not present yet — skip
        }
    }
}
