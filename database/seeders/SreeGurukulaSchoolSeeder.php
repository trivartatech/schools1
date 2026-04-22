<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Organization;
use App\Models\School;

/**
 * SreeGurukulaSchoolSeeder
 * -----------------------------------------------------------------------------
 * Bootstraps a single tenant for Sree Gurukula International School
 * (sreegurukulainternationalschool.com).
 *
 * Creates:
 *   - Organization (trust-level record)
 *   - School with default feature flags enabled
 *   - Current academic year (Apr - Mar)
 *   - Super admin, school admin, and principal users
 *
 * Run: php artisan db:seed --class=SreeGurukulaSchoolSeeder
 */
class SreeGurukulaSchoolSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'sree-gurukula-international'],
            [
                'name'    => 'Sree Gurukula International Trust',
                'email'   => 'info@sreegurukulainternationalschool.com',
                'phone'   => '',
                'address' => '',
                'website' => 'https://sreegurukulainternationalschool.com',
            ]
        );

        $school = School::firstOrCreate(
            ['slug' => 'sree-gurukula-international-school'],
            [
                'organization_id' => $org->id,
                'name'            => 'Sree Gurukula International School',
                'code'            => 'SGIS001',
                'board'           => 'CBSE',
                'email'           => 'admin@sreegurukulainternationalschool.com',
                'phone'           => '',
                'address'         => '',
                'city'            => '',
                'state'           => '',
                'pincode'         => '',
                'website'         => 'https://sreegurukulainternationalschool.com',
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
                    'footer_credit' => '© ' . date('Y') . ' Sree Gurukula International School. All rights reserved.',
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
            ['email' => 'superadmin@sreegurukulainternationalschool.com'],
            [
                'name'      => 'Super Administrator',
                'password'  => Hash::make('ChangeMe@2026'),
                'user_type' => 'super_admin',
                'is_active' => true,
            ]
        );
        $this->safeAssignRole($superAdmin, 'super_admin');

        $schoolAdmin = User::firstOrCreate(
            ['email' => 'admin@sreegurukulainternationalschool.com'],
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
            ['email' => 'principal@sreegurukulainternationalschool.com'],
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

        $this->command->info('Sree Gurukula International School seeded.');
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
