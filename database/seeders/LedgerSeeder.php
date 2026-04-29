<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class LedgerSeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        Schema::disableForeignKeyConstraints();
        DB::table('ledgers')->where('school_id', $schoolId)->delete();
        DB::table('ledger_types')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // ── 1. Ledger Types ────────────────────────────────────────────────────
        $ledgerTypesData = [
            ['name' => 'Income',          'nature' => 'credit', 'is_system' => true,  'desc' => 'All income sources — fees, grants, donations'],
            ['name' => 'Expense',         'nature' => 'debit',  'is_system' => true,  'desc' => 'All operating expenses'],
            ['name' => 'Asset',           'nature' => 'debit',  'is_system' => true,  'desc' => 'School assets — fixed and current'],
            ['name' => 'Liability',       'nature' => 'credit', 'is_system' => true,  'desc' => 'Liabilities — payables, loans'],
            ['name' => 'Bank & Cash',     'nature' => 'debit',  'is_system' => true,  'desc' => 'Bank accounts and cash in hand'],
            ['name' => 'Capital',         'nature' => 'credit', 'is_system' => true,  'desc' => 'Capital and reserves'],
            ['name' => 'Staff Payroll',   'nature' => 'debit',  'is_system' => false, 'desc' => 'Salary and payroll disbursements'],
            ['name' => 'Grants & Funds',  'nature' => 'credit', 'is_system' => false, 'desc' => 'Government and trust grants'],
        ];

        $typeIds = [];
        foreach ($ledgerTypesData as $lt) {
            $typeIds[$lt['name']] = DB::table('ledger_types')->insertGetId([
                'school_id'   => $schoolId,
                'name'        => $lt['name'],
                'nature'      => $lt['nature'],
                'description' => $lt['desc'],
                'is_system'   => $lt['is_system'],
                'created_at'  => $now, 'updated_at' => $now,
            ]);
        }

        // ── 2. Ledgers ─────────────────────────────────────────────────────────
        $ledgersData = [
            // Income
            ['type' => 'Income',        'name' => 'Tuition Fee Income',        'code' => 'INC-TF',  'opening' => 0,        'ob_type' => 'credit', 'system' => true],
            ['type' => 'Income',        'name' => 'Transport Fee Income',       'code' => 'INC-TR',  'opening' => 0,        'ob_type' => 'credit', 'system' => true],
            ['type' => 'Income',        'name' => 'Hostel Fee Income',          'code' => 'INC-HS',  'opening' => 0,        'ob_type' => 'credit', 'system' => false],
            ['type' => 'Income',        'name' => 'Admission Fee Income',       'code' => 'INC-AD',  'opening' => 0,        'ob_type' => 'credit', 'system' => false],
            ['type' => 'Income',        'name' => 'Miscellaneous Income',       'code' => 'INC-MS',  'opening' => 0,        'ob_type' => 'credit', 'system' => false],
            // Expenses
            ['type' => 'Expense',       'name' => 'Staff Salaries',             'code' => 'EXP-SAL', 'opening' => 0,        'ob_type' => 'debit',  'system' => true],
            ['type' => 'Expense',       'name' => 'Electricity & Utilities',    'code' => 'EXP-ELC', 'opening' => 0,        'ob_type' => 'debit',  'system' => false],
            ['type' => 'Expense',       'name' => 'Maintenance & Repairs',      'code' => 'EXP-MNT', 'opening' => 0,        'ob_type' => 'debit',  'system' => false],
            ['type' => 'Expense',       'name' => 'Stationery & Supplies',      'code' => 'EXP-STA', 'opening' => 0,        'ob_type' => 'debit',  'system' => false],
            ['type' => 'Expense',       'name' => 'Library Books & Journals',   'code' => 'EXP-LIB', 'opening' => 0,        'ob_type' => 'debit',  'system' => false],
            ['type' => 'Expense',       'name' => 'Lab Equipment & Chemicals',  'code' => 'EXP-LAB', 'opening' => 0,        'ob_type' => 'debit',  'system' => false],
            ['type' => 'Expense',       'name' => 'Transport Fuel & Maintenance','code'=> 'EXP-TRF', 'opening' => 0,        'ob_type' => 'debit',  'system' => false],
            ['type' => 'Expense',       'name' => 'Events & Activities',        'code' => 'EXP-EVT', 'opening' => 0,        'ob_type' => 'debit',  'system' => false],
            // Assets
            ['type' => 'Asset',         'name' => 'Furniture & Fixtures',       'code' => 'AST-FF',  'opening' => 850000,   'ob_type' => 'debit',  'system' => false],
            ['type' => 'Asset',         'name' => 'Computer & IT Equipment',    'code' => 'AST-IT',  'opening' => 420000,   'ob_type' => 'debit',  'system' => false],
            ['type' => 'Asset',         'name' => 'Library Books (Asset)',       'code' => 'AST-LB',  'opening' => 180000,   'ob_type' => 'debit',  'system' => false],
            ['type' => 'Asset',         'name' => 'School Vehicles',             'code' => 'AST-VH',  'opening' => 3500000,  'ob_type' => 'debit',  'system' => false],
            // Bank & Cash
            ['type' => 'Bank & Cash',   'name' => 'SBI Current Account',        'code' => 'BNK-SBI', 'opening' => 2500000,  'ob_type' => 'debit',  'system' => true],
            ['type' => 'Bank & Cash',   'name' => 'HDFC Savings Account',        'code' => 'BNK-HDF', 'opening' => 850000,   'ob_type' => 'debit',  'system' => false],
            ['type' => 'Bank & Cash',   'name' => 'Petty Cash',                  'code' => 'CSH-PCT', 'opening' => 25000,    'ob_type' => 'debit',  'system' => false],
            // Liabilities
            ['type' => 'Liability',     'name' => 'Salary Payable',              'code' => 'LIA-SAL', 'opening' => 0,        'ob_type' => 'credit', 'system' => true],
            ['type' => 'Liability',     'name' => 'Fee Advance (Deposits)',       'code' => 'LIA-DEP', 'opening' => 120000,   'ob_type' => 'credit', 'system' => false],
            ['type' => 'Liability',     'name' => 'TDS Payable',                  'code' => 'LIA-TDS', 'opening' => 0,        'ob_type' => 'credit', 'system' => false],
            // Capital
            ['type' => 'Capital',       'name' => 'School Capital Fund',          'code' => 'CAP-SCH', 'opening' => 10000000, 'ob_type' => 'credit', 'system' => true],
            ['type' => 'Capital',       'name' => 'Development Fund Reserve',     'code' => 'CAP-DEV', 'opening' => 1500000,  'ob_type' => 'credit', 'system' => false],
            // Grants
            ['type' => 'Grants & Funds','name' => 'Government Grant',             'code' => 'GRT-GOV', 'opening' => 0,        'ob_type' => 'credit', 'system' => false],
            ['type' => 'Staff Payroll', 'name' => 'Teaching Staff Payroll',       'code' => 'PAY-TCH', 'opening' => 0,        'ob_type' => 'debit',  'system' => false],
            ['type' => 'Staff Payroll', 'name' => 'Non-Teaching Staff Payroll',   'code' => 'PAY-NTC', 'opening' => 0,        'ob_type' => 'debit',  'system' => false],
        ];

        foreach ($ledgersData as $l) {
            DB::table('ledgers')->insert([
                'school_id'            => $schoolId,
                'ledger_type_id'       => $typeIds[$l['type']],
                'name'                 => $l['name'],
                'code'                 => $l['code'],
                'opening_balance'      => $l['opening'],
                'opening_balance_type' => $l['ob_type'],
                'is_system'            => $l['system'],
                'is_active'            => true,
                'created_at'           => $now, 'updated_at' => $now,
            ]);
        }

        $this->command->info('✅ Ledger seeded!');
        $this->command->info('   - ' . count($ledgerTypesData) . ' Ledger Types');
        $this->command->info('   - ' . count($ledgersData) . ' Ledgers');
    }
}
