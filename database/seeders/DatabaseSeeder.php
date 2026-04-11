<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DemoDataSeeder::class,
            SchoolDummyDataSeeder::class,
            FeeDummyDataSeeder::class,
            FeeConcessionTypeSeeder::class,
            HRStaffSeeder::class,
            AttendanceSeeder::class,
            CBSEDataSeeder::class,
            GradingSystemSeeder::class,
            CommunicationTemplateSeeder::class,
            StudentApplicationsSeeder::class,
            ExaminationModuleSeeder::class,
            DummyDataSeeder::class,
        ]);
    }
}
