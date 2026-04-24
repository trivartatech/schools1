<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the month/day term the applicant selected at registration time so the
 * approval step can create a TransportStudentAllocation with the correct
 * pro-rata transport fee.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('student_applications', 'transport_months')) {
                $table->unsignedTinyInteger('transport_months')->nullable()->after('transport_pickup_type');
            }
            if (!Schema::hasColumn('student_applications', 'transport_days')) {
                $table->unsignedTinyInteger('transport_days')->nullable()->after('transport_months');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            if (Schema::hasColumn('student_applications', 'transport_days')) {
                $table->dropColumn('transport_days');
            }
            if (Schema::hasColumn('student_applications', 'transport_months')) {
                $table->dropColumn('transport_months');
            }
        });
    }
};
