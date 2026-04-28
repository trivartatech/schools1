<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the concession system to cover all four fee streams.
 *
 * Adds fee_type to fee_concessions so a school can scope a concession to
 * tuition / transport / hostel / stationary. Existing rows are backfilled
 * to 'tuition' (current behaviour). Adds nullable concession_id to the
 * three non-tuition payment tables so the discount can be tracked when
 * applied during collection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_concessions', function (Blueprint $table) {
            if (! Schema::hasColumn('fee_concessions', 'fee_type')) {
                $table->string('fee_type', 20)->default('tuition')->after('academic_year_id');
                $table->index(['school_id', 'fee_type']);
            }
        });

        foreach (['transport_fee_payments', 'hostel_fee_payments', 'stationary_fee_payments'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'concession_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('concession_id')
                        ->nullable()
                        ->after('discount')
                        ->constrained('fee_concessions')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['transport_fee_payments', 'hostel_fee_payments', 'stationary_fee_payments'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'concession_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['concession_id']);
                    $table->dropColumn('concession_id');
                });
            }
        }

        Schema::table('fee_concessions', function (Blueprint $table) {
            if (Schema::hasColumn('fee_concessions', 'fee_type')) {
                $table->dropIndex(['school_id', 'fee_type']);
                $table->dropColumn('fee_type');
            }
        });
    }
};
