<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Organization;
use App\Models\School;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Organization & School
        $org = Organization::create([
            'name'    => 'school1 Org',
            'slug'    => 'school1-org',
            'email'   => 'org@school1.com',
            'phone'   => '0112345678',
            'address' => '45, Sector 12, Dwarka, New Delhi - 110075',
            'website' => 'https://school1org.edu.in',
        ]);

        $school = School::create([
            'organization_id' => $org->id,
            'name'            => 'school1',
            'slug'            => 'school1',
            'code'            => 'SCH001',
            'board'           => 'CBSE',
            'email'           => 'admin@school1.com',
            'phone'           => '0112345679',
            'address'         => '45, Sector 12, Dwarka',
            'city'            => 'New Delhi',
            'state'           => 'Delhi',
            'pincode'         => '110075',
            'website'         => 'https://school1.edu.in',
            'principal_name'  => 'School Principal',
            'timezone'        => 'Asia/Kolkata',
            'currency'        => 'INR',
            'language'        => 'en',
        ]);

        // 2. Super Admin
        $superAdmin = User::create([
            'name'      => 'Super Administrator',
            'email'     => 'admin@school.com',
            'phone'     => '9876543210',
            'password'  => Hash::make('password'),
            'user_type' => 'super_admin',
            'is_active' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        // 3. Org Admin
        $orgAdmin = User::create([
            'organization_id' => $org->id,
            'name'      => 'Org Administrator',
            'email'     => 'org@school.com',
            'password'  => Hash::make('password'),
            'user_type' => 'admin',
            'is_active' => true,
        ]);
        $orgAdmin->assignRole('admin');

        // 4. Principal
        $principal = User::create([
            'school_id'       => $school->id,
            'organization_id' => $org->id,
            'name'      => 'School Principal',
            'email'     => 'principal@school1.com',
            'password'  => Hash::make('password'),
            'user_type' => 'principal',
            'is_active' => true,
        ]);
        $principal->assignRole('principal');

        // 5. Teacher
        $teacher = User::create([
            'school_id'       => $school->id,
            'organization_id' => $org->id,
            'name'      => 'John Doe (Teacher)',
            'email'     => 'teacher@school1.com',
            'password'  => Hash::make('password'),
            'user_type' => 'teacher',
            'is_active' => true,
        ]);
        $teacher->assignRole('teacher');

        $this->command->info('Demo Data (Org, School: school1, Admin, Teacher) seeded successfully!');
    }
}
