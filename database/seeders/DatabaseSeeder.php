<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,          // roles + permissions (must run first)
            GradingSystemSeeder::class,
            FeeConcessionTypeSeeder::class,
            CommunicationTemplateSeeder::class,
            GenericSchoolSeeder::class,            // organization + school + default admin users
        ]);
    }
}
