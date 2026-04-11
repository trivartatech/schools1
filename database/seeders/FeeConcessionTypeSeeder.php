<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeeConcessionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // For testing/seeding, we'll assign to the first school
        $schoolId = \App\Models\School::first()->id ?? 1;

        $types = [
            ['name' => 'Sibling Discount',       'description' => 'Discount applied for second/third child in the same school.'],
            ['name' => 'Merit Scholarship',      'description' => 'Scholarship awarded for academic excellence.'],
            ['name' => 'Sport Quota Discount',   'description' => 'Concession for students admitted under sports quota.'],
            ['name' => 'Staff Ward Concession',  'description' => 'Discount applied for children of school employees.'],
            ['name' => 'Early Bird Discount',    'description' => 'One-time discount for early fee payment.'],
            ['name' => 'Economically Weaker Section (EWS)', 'description' => 'As per govt regulations.'],
            ['name' => 'Management Quota',       'description' => 'Special discount approved by the trust.'],
            ['name' => 'Single Girl Child',      'description' => 'Concession for single girl child.'],
            ['name' => 'RTE Concession',         'description' => 'Right to Education Act quota concession.'],
        ];

        foreach ($types as $type) {
            \App\Models\FeeConcessionType::firstOrCreate([
                'school_id' => $schoolId,
                'name'      => $type['name'],
            ], [
                'description' => $type['description'],
                'is_active'   => true,
            ]);
        }
    }
}
