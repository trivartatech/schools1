<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('exam_marks', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('transfer_certificates', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('student_academic_histories', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('student_documents', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('student_leaves', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('exam_marks', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('transfer_certificates', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('student_academic_histories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('student_documents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('student_leaves', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
