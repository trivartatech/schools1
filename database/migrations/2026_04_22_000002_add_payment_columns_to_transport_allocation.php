<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track transport fee collection directly on the allocation row.
 *
 *  transport_fee     — amount due (already existed)
 *  amount_paid       — running total across all receipts
 *  discount          — running discount total
 *  fine              — running fine total
 *  balance           — transport_fee - discount + fine - amount_paid
 *  payment_status    — unpaid | partial | paid | waived
 *  last_payment_date — date of most recent receipt, for reports
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_student_allocation', function (Blueprint $table) {
            $table->decimal('amount_paid', 10, 2)->default(0)->after('transport_fee');
            $table->decimal('discount', 10, 2)->default(0)->after('amount_paid');
            $table->decimal('fine', 10, 2)->default(0)->after('discount');
            $table->decimal('balance', 10, 2)->default(0)->after('fine');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'waived'])
                  ->default('unpaid')->after('balance');
            $table->date('last_payment_date')->nullable()->after('payment_status');
            $table->index(['school_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('transport_student_allocation', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'payment_status']);
            $table->dropColumn([
                'amount_paid',
                'discount',
                'fine',
                'balance',
                'payment_status',
                'last_payment_date',
            ]);
        });
    }
};
