<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['school_id', 'date'], 'idx_attn_school_date');
            $table->index(['school_id', 'academic_year_id', 'student_id'], 'idx_attn_school_year_student');
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->index(['school_id', 'payment_date', 'status'], 'idx_fees_school_date_status');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->index(['school_id', 'admission_date', 'status'], 'idx_students_school_date_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attn_school_date');
            $table->dropIndex('idx_attn_school_year_student');
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropIndex('idx_fees_school_date_status');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('idx_students_school_date_status');
        });
    }
};
