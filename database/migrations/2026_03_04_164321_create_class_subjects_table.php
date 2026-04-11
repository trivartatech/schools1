<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            // Override whether this assignment is co-scholastic at the class level
            $table->boolean('is_co_scholastic')->default(false);
            $table->timestamps();

            // A subject can only be assigned once per class+section combination
            $table->unique(['course_class_id', 'section_id', 'subject_id'], 'unique_class_section_subject');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
    }
};
