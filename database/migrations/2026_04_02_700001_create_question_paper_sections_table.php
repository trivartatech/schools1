<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_paper_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_paper_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('question_type');
            $table->unsignedInteger('marks_per_question');
            $table->unsignedInteger('num_questions');
            $table->string('instructions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_paper_sections');
    }
};
