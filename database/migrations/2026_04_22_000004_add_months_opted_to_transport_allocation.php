<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `months_opted` to transport_student_allocation so a student can opt
 * for a partial term (e.g. 5.5 of 10 standard months). `transport_fee`
 * is now computed as round(stop.fee / standard_months * months_opted, 2).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('transport_student_allocation', function (Blueprint $table) {
            if (!Schema::hasColumn('transport_student_allocation', 'months_opted')) {
                $table->decimal('months_opted', 4, 2)->default(10.00)->after('transport_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transport_student_allocation', function (Blueprint $table) {
            if (Schema::hasColumn('transport_student_allocation', 'months_opted')) {
                $table->dropColumn('months_opted');
            }
        });
    }
};
