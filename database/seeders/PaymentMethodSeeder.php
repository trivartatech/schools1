<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * PaymentMethodSeeder
 * -----------------------------------------------------------------------------
 * Populates the `payment_methods` table for every school with the standard
 * 9 default methods (Cash, Cheque, Online, UPI, Card, Demand Draft, NEFT,
 * RTGS, Bank Transfer). The fee/expense/payroll receipt UIs read this table
 * to render the payment-mode dropdown, and validate the submitted code
 * against payment_methods.code.
 *
 * Idempotent — safe to re-run. Uses (school_id, code) as the dedupe key.
 *
 * Note: the create_payment_methods_table migration also seeds these defaults,
 * but only for schools that exist at migrate-time. New schools (created
 * after migration) won't have rows. This seeder closes that gap and gives
 * us a re-runnable explicit step.
 */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $defaults = [
            ['code' => 'cash',          'label' => 'Cash',          'sort_order' => 1],
            ['code' => 'cheque',        'label' => 'Cheque',        'sort_order' => 2],
            ['code' => 'online',        'label' => 'Online',        'sort_order' => 3],
            ['code' => 'upi',           'label' => 'UPI',           'sort_order' => 4],
            ['code' => 'card',          'label' => 'Card',          'sort_order' => 5],
            ['code' => 'dd',            'label' => 'Demand Draft',  'sort_order' => 6],
            ['code' => 'neft',          'label' => 'NEFT',          'sort_order' => 7],
            ['code' => 'rtgs',          'label' => 'RTGS',          'sort_order' => 8],
            ['code' => 'bank_transfer', 'label' => 'Bank Transfer', 'sort_order' => 9],
        ];

        $schoolIds = DB::table('schools')->pluck('id');
        $insertedTotal = 0;
        $existingTotal = 0;

        foreach ($schoolIds as $schoolId) {
            foreach ($defaults as $row) {
                $exists = DB::table('payment_methods')
                    ->where('school_id', $schoolId)
                    ->where('code', $row['code'])
                    ->exists();

                if ($exists) {
                    $existingTotal++;
                    continue;
                }

                DB::table('payment_methods')->insert(array_merge($row, [
                    'school_id'  => $schoolId,
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $insertedTotal++;
            }
        }

        $this->command->info('✅ Payment methods seeded.');
        $this->command->info('   - Schools processed: ' . $schoolIds->count());
        $this->command->info('   - Newly inserted:    ' . $insertedTotal);
        $this->command->info('   - Already present:   ' . $existingTotal);
    }
}
