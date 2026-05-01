<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convert fee_payments.payment_mode and expenses.payment_mode from
 * MySQL ENUM('cash','cheque','online','upi','dd','card') to VARCHAR(50).
 *
 * Why:
 *   Admins can register custom payment methods via Finance → Payment
 *   Methods (e.g. phonepe, paytm, gpay, wallet). The validators on the
 *   Hostel / Transport / Stationary fee-collection paths accept any
 *   active code from payment_methods (Rule::exists). For two of the
 *   five fee tables the column type itself is a hard ENUM in MySQL —
 *   even after removing the App\Enums\PaymentMode model cast (commit
 *   a3cc4ff), MySQL rejects the insert with "Data truncated for column
 *   'payment_mode'" before Eloquent can persist anything.
 *
 *   The other 3 fee streams (transport_fee_payments, hostel_fee_payments,
 *   stationary_fee_payments) were already created with VARCHAR and are
 *   not affected.
 *
 * Width:
 *   VARCHAR(50) matches payment_methods.code (also VARCHAR(50)) — that
 *   table is the dynamic source of truth, so payment_mode mirrors its
 *   width to remove any chance of truncation in the future.
 *
 * Driver gate:
 *   ENUM is a MySQL/MariaDB-specific type. PostgreSQL would use
 *   CREATE TYPE (handled differently by Laravel's schema builder) and
 *   SQLite stores ENUM as TEXT with no enforcement, so the bug only
 *   exists on MySQL. Skip cleanly on the other drivers.
 *
 * Reload required after deploy:
 *   sudo service php8.3-fpm reload     (OPcache for the model cast change)
 *   php artisan migrate                (this migration)
 *   In that order — the model cast must be off before MySQL accepts
 *   custom codes through Eloquent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return; // PostgreSQL / SQLite: not affected.
        }

        DB::statement("ALTER TABLE fee_payments MODIFY payment_mode VARCHAR(50) NOT NULL DEFAULT 'cash'");
        DB::statement("ALTER TABLE expenses     MODIFY payment_mode VARCHAR(50) NOT NULL DEFAULT 'cash'");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // Coerce any custom payment_mode values back into the original
        // ENUM domain before re-applying the constraint, otherwise this
        // ALTER would fail on exactly the rows the migration was added
        // to support. We collapse unknown codes to 'online' since that's
        // the closest legacy bucket for digital wallets / UPI providers.
        $legacyDomain = ['cash', 'cheque', 'online', 'upi', 'dd', 'card'];

        DB::table('fee_payments')
            ->whereNotIn('payment_mode', $legacyDomain)
            ->update(['payment_mode' => 'online']);
        DB::table('expenses')
            ->whereNotIn('payment_mode', $legacyDomain)
            ->update(['payment_mode' => 'online']);

        DB::statement("ALTER TABLE fee_payments MODIFY payment_mode ENUM('cash','cheque','online','upi','dd','card') NOT NULL DEFAULT 'cash'");
        DB::statement("ALTER TABLE expenses     MODIFY payment_mode ENUM('cash','cheque','online','upi','dd','card') NOT NULL DEFAULT 'cash'");
    }
};
