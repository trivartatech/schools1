<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Organization;
use App\Models\School;

/**
 * GenericSchoolSeeder
 * -----------------------------------------------------------------------------
 * Bootstraps a single tenant (organization + school + 3 default admin users)
 * from values in .env (read via config('school.*')).
 *
 * This is the generic replacement for the old per-school seeders
 * (SreeGurukulaSchoolSeeder / NationalEnglishSchoolSeeder / SahyadriSchoolSeeder).
 *
 * Creates:
 *   - Organization (trust-level record)
 *   - School with default feature flags enabled
 *   - Current academic year (Apr - Mar)
 *   - Super admin, school admin, and principal users
 *
 * Run: php artisan db:seed --class=GenericSchoolSeeder
 *
 * Idempotent: uses firstOrCreate everywhere — safe to re-run.
 */
class GenericSchoolSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles & permissions exist before we try to assign them to users.
        // This makes GenericSchoolSeeder safe to run standalone (without DatabaseSeeder first).
        $this->call(RolePermissionSeeder::class);

        $cfg = config('school');

        $org = Organization::firstOrCreate(
            ['slug' => $cfg['organization']['slug']],
            [
                'name'    => $cfg['organization']['name'],
                'email'   => $cfg['organization']['email'],
                'phone'   => '',
                'address' => '',
                'website' => $cfg['organization']['website'],
            ]
        );

        $school = School::firstOrCreate(
            ['slug' => $cfg['slug']],
            [
                'organization_id' => $org->id,
                'name'            => $cfg['name'],
                'code'            => $cfg['code'],
                'board'           => $cfg['board'],
                'email'           => $cfg['email'],
                'phone'           => $cfg['phone'],
                'address'         => $cfg['address'],
                'city'            => $cfg['city'],
                'state'           => $cfg['state'],
                'pincode'         => $cfg['pincode'],
                'website'         => $cfg['website'],
                'principal_name'  => $cfg['principal_name'],
                'timezone'        => $cfg['timezone'],
                'currency'        => $cfg['currency'],
                'language'        => $cfg['language'],
                'status'          => 'active',
                // Hostel + Transport are edition-gated (config/features.php → ERP_EDITION).
                // All other features stay on for every install.
                'features'        => array_merge([
                    'attendance'     => true,
                    'fee'            => true,
                    'exam'           => true,
                    'communication'  => true,
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
                    'library'        => true,
                    'stationary'     => true,
                ], config(
                    'features.editions.' . config('features.edition', 'full'),
                    ['hostel' => true, 'transport' => true]
                )),
                'settings'        => [
                    'footer_credit' => '© ' . date('Y') . ' ' . $cfg['name'] . '. All rights reserved.',
                ],
            ]
        );

        $year = (int) date('Y');
        $school->academicYears()->firstOrCreate(
            ['name' => $year . '-' . ($year + 1)],
            [
                'start_date' => $year . '-04-01',
                'end_date'   => ($year + 1) . '-03-31',
                'is_current' => true,
                'status'     => 'active',
            ]
        );

        $defaultPwd = Hash::make($cfg['admins']['default_password']);

        $superAdmin = User::firstOrCreate(
            ['email' => $cfg['admins']['super_admin_email']],
            [
                'name'      => 'Super Administrator',
                'password'  => $defaultPwd,
                'user_type' => 'super_admin',
                'is_active' => true,
            ]
        );
        $this->safeAssignRole($superAdmin, 'super_admin');

        $schoolAdmin = User::firstOrCreate(
            ['email' => $cfg['admins']['admin_email']],
            [
                'school_id'       => $school->id,
                'organization_id' => $org->id,
                'name'            => 'School Administrator',
                'password'        => $defaultPwd,
                'user_type'       => 'school_admin',
                'is_active'       => true,
            ]
        );
        $this->safeAssignRole($schoolAdmin, 'school_admin');
        $this->safeAssignRole($schoolAdmin, 'admin');

        $principal = User::firstOrCreate(
            ['email' => $cfg['admins']['principal_email']],
            [
                'school_id'       => $school->id,
                'organization_id' => $org->id,
                'name'            => 'Principal',
                'password'        => $defaultPwd,
                'user_type'       => 'principal',
                'is_active'       => true,
            ]
        );
        $this->safeAssignRole($principal, 'principal');

        $this->command->info($cfg['name'] . ' seeded.');
        $this->command->warn('Default password is "' . $cfg['admins']['default_password'] . '" — change it after first login.');
    }

    private function safeAssignRole(User $user, string $role): void
    {
        try {
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }
        } catch (\Throwable $e) {
            // role not present yet — skip silently
        }
    }
}
