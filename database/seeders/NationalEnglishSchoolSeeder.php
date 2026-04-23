<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Organization;
use App\Models\School;

/**
 * NationalEnglishSchoolSeeder
 * -----------------------------------------------------------------------------
 * Bootstraps a single tenant for National English School, Chitradurga.
 *
 * Creates:
 *   - Organization (trust-level record)
 *   - School with default feature flags enabled
 *   - Current academic year (Apr - Mar)
 *   - Super admin, school admin, and principal users
 *
 * Run: php artisan db:seed --class=NationalEnglishSchoolSeeder
 */
class NationalEnglishSchoolSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'national-english-school-trust'],
            [
                'name'    => 'National English School Trust',
                'email'   => 'info@nescta.in',
                'phone'   => '',
                'address' => 'Horpete, Vijayanagar, Chitradurga, Karnataka 577502',
                'website' => 'https://nescta.in',
            ]
        );

        $school = School::firstOrCreate(
            ['slug' => 'national-english-school'],
            [
                'organization_id' => $org->id,
                'name'            => 'National English School',
                'code'            => 'NES001',
                'board'           => 'State',
                'email'           => 'admin@nescta.in',
                'phone'           => '',
                'address'         => 'Horpete, Vijayanagar',
                'city'            => 'Chitradurga',
                'state'           => 'Karnataka',
                'pincode'         => '577502',
                'website'         => 'https://nescta.in',
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
                    'footer_credit' => '© ' . date('Y') . ' National English School, Chitradurga. All rights reserved.',
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
            ['email' => 'superadmin@nescta.in'],
            [
                'name'      => 'Super Administrator',
                'password'  => Hash::make('ChangeMe@2026'),
                'user_type' => 'super_admin',
                'is_active' => true,
            ]
        );
        $this->safeAssignRole($superAdmin, 'super_admin');

        $schoolAdmin = User::firstOrCreate(
            ['email' => 'admin@nescta.in'],
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
            ['email' => 'principal@nescta.in'],
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

        $this->command->info('National English School, Chitradurga seeded.');
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
