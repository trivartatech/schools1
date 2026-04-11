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
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->boolean('is_optional')->default(false)->after('due_date');
            $table->enum('student_type', ['all', 'new', 'old'])->default('all')->after('is_optional');
            $table->enum('gender', ['all', 'male', 'female'])->default('all')->after('student_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropColumn(['is_optional', 'student_type', 'gender']);
        });
    }
};
