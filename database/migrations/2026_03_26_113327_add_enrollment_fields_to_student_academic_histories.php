<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_academic_histories', function (Blueprint $table) {
            $table->string('enrollment_type', 50)->default('Regular')->after('status'); // Regular, Transfer, Lateral
            $table->string('student_type', 50)->default('Old Student')->after('enrollment_type'); // New Student, Old Student
        });
    }

    public function down(): void
    {
        Schema::table('student_academic_histories', function (Blueprint $table) {
            $table->dropColumn(['enrollment_type', 'student_type']);
        });
    }
};
