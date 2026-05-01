<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DummyDataSeeder — DEPRECATED / NO-OP
 * -----------------------------------------------------------------------------
 * This seeder has been retired as of 2026-05-01.
 *
 * Why it was retired (8 issues found in audit):
 *   1. Duplicate fee groups — created "Academic Fees" + "Transport Fees" on top
 *      of FeeDummyDataSeeder's identical groups, producing double rows every run.
 *   2. Duplicate fee heads — "Tuition Fee" and "Bus Fare" clash with
 *      FeeDummyDataSeeder's "Tuition Fee" and "Bus Fee".
 *   3. Duplicate fee structures — one record per class per head, created again
 *      on top of FeeDummyDataSeeder's three-installment structures.
 *   4. Only 10 of 351 students received payment records (->take(10)).
 *   5. Attendance overwrite — used updateOrCreate for the last 7 days with
 *      random statuses, destroying AttendanceSeeder's carefully-profiled data.
 *   6. Hard-coded $adminId = 1 (super-admin on a multi-tenant install).
 *   7. No cleanup before insert → duplicates accumulate on every re-run.
 *   8. Legacy SQLite date format comment ("Y-m-d 00:00:00") left in MySQL code.
 *
 * What replaced it:
 *   • FeeDummyDataSeeder  — realistic fee groups, heads, structures, and
 *     payments for ALL students with good/partial/late/defaulter scenarios,
 *     15 % concession coverage, and proper receipt numbers.
 *   • AttendanceSeeder    — 90-day profiled attendance per student (present /
 *     absent / late / half_day) with realistic percentages.
 *   • PaymentMethodSeeder — seeds payment_methods for all schools so the
 *     payment-mode dropdown is always populated.
 *
 * The class is kept as a no-op so DatabaseSeeder::run() doesn't need touching.
 */
class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('⚠  DummyDataSeeder is deprecated and does nothing.');
        $this->command->line('   Fee data  → FeeDummyDataSeeder');
        $this->command->line('   Attendance → AttendanceSeeder');
        $this->command->line('   See the docblock in this file for full details.');
    }
}
