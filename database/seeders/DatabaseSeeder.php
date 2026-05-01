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
     *
     * Hostel and Transport seeders are gated on the install's edition
     * (config/features.php → ERP_EDITION). Tables are still migrated for
     * every edition; we just skip seeding sample data when the module
     * is disabled. This keeps edition upgrades a single SQL flag flip.
     */
    public function run(): void
    {
        $edition  = config('features.edition', 'full');
        $features = config("features.editions.{$edition}", []);

        $seeders = [
            RolePermissionSeeder::class,
            DemoDataSeeder::class,
            SchoolDummyDataSeeder::class,
            FeeDummyDataSeeder::class,
            FeeConcessionTypeSeeder::class,
            HRStaffSeeder::class,
            InchargeDummyDataSeeder::class,
            AttendanceSeeder::class,
            CBSEDataSeeder::class,
            GradingSystemSeeder::class,
            CommunicationTemplateSeeder::class,
            StudentApplicationsSeeder::class,
            ExaminationModuleSeeder::class,
            DummyDataSeeder::class,
        ];

        if (($features['transport'] ?? true) === true) {
            $seeders[] = TransportSeeder::class;
        }
        if (($features['hostel'] ?? true) === true) {
            $seeders[] = HostelSeeder::class;
        }

        $seeders = array_merge($seeders, [
            AcademicResourcesSeeder::class,
            FrontOfficeSeeder::class,
            AnnouncementsSeeder::class,
            StaffAttendanceSeeder::class,
            StudentHealthDocsSeeder::class,
            ChatSeeder::class,
            LedgerSeeder::class,
        ]);

        $this->call($seeders);
    }
}
