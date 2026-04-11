<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hostel_students', function (Blueprint $table) {
            $table->foreignId('fee_payment_id')->nullable()->constrained('fee_payments')->nullOnDelete()->after('status');
        });

        // Ensure is_hostel_fee column exists on fee_heads (may have been added earlier)
        if (!Schema::hasColumn('fee_heads', 'is_hostel_fee')) {
            Schema::table('fee_heads', function (Blueprint $table) {
                $table->boolean('is_hostel_fee')->default(false)->after('is_transport_fee');
            });
        }
    }

    public function down(): void
    {
        Schema::table('hostel_students', function (Blueprint $table) {
            $table->dropForeign(['fee_payment_id']);
            $table->dropColumn('fee_payment_id');
        });
    }
};
