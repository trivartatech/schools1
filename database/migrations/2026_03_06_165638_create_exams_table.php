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
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scholastic_grading_system_id')->nullable()->constrained('grading_systems')->nullOnDelete();
            $table->foreignId('co_scholastic_grading_system_id')->nullable()->constrained('grading_systems')->nullOnDelete();
            $table->string('name');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
