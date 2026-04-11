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
        Schema::create('exam_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_term_id')->constrained('exam_terms')->cascadeOnDelete();
            
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('display_name')->nullable();
            
            $table->decimal('weightage', 5, 2)->default(100.00)->comment('Weightage percentage for final report');
            $table->enum('classification', ['main', 'periodic', 'unit_test'])->default('main');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_types');
    }
};
