<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        $academicYearId = DB::table('academic_years')->where('school_id', $schoolId)->where('is_current', true)->value('id')
                       ?? DB::table('academic_years')->where('school_id', $schoolId)->where('status', 'active')->value('id');

        if (!$academicYearId) {
            $this->command->error('BudgetSeeder: no active academic year.');
            return;
        }

        Schema::disableForeignKeyConstraints();
        DB::table('budgets')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // Look up expense categories created by HRStaffSeeder if any
        $expenseCategories = DB::table('expense_categories')->where('school_id', $schoolId)->pluck('id', 'name')->toArray();

        $editionFeatures = config('features.editions.' . config('features.edition', 'full'), []);

        $budgets = [
            ['name' => 'Annual Operating Budget',     'amount' => 12500000, 'category' => null,                       'notes' => 'Top-line annual budget for FY school operations.'],
            ['name' => 'Salaries & Payroll',          'amount' => 6500000,  'category' => 'Salaries',                  'notes' => 'Annual budget for staff salaries and benefits.'],
            ['name' => 'Utilities & Maintenance',     'amount' => 850000,   'category' => 'Utilities',                 'notes' => 'Electricity, water, internet, repairs.'],
            ['name' => 'Academic Resources',          'amount' => 600000,   'category' => 'Academic',                  'notes' => 'Books, lab consumables, learning materials.'],
            ['name' => 'Sports & Co-curricular',      'amount' => 400000,   'category' => 'Sports',                    'notes' => 'Sports equipment, tournaments, cultural events.'],
        ];

        if (($editionFeatures['transport'] ?? true) === true) {
            $budgets[] = ['name' => 'Transport Operations',     'amount' => 1200000, 'category' => 'Transport', 'notes' => 'Bus fuel, driver salaries, vehicle maintenance.'];
        }
        if (($editionFeatures['hostel'] ?? true) === true) {
            $budgets[] = ['name' => 'Hostel & Mess Operations', 'amount' => 950000,  'category' => 'Hostel',    'notes' => 'Mess provisions, hostel maintenance.'];
        }

        $budgets[] = ['name' => 'IT Infrastructure', 'amount' => 350000, 'category' => null, 'notes' => 'Hardware refresh, software licenses.'];

        foreach ($budgets as $b) {
            $catId = ($b['category'] && isset($expenseCategories[$b['category']]))
                ? $expenseCategories[$b['category']]
                : null;

            DB::table('budgets')->insert([
                'school_id'           => $schoolId,
                'academic_year_id'    => $academicYearId,
                'expense_category_id' => $catId,
                'name'                => $b['name'],
                'amount'              => $b['amount'],
                'notes'               => $b['notes'],
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }

        $this->command->info('✅ Budgets seeded: ' . count($budgets) . ' budget entries.');
    }
}
