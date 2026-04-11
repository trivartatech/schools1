<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_paper_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('question_paper_sections')->cascadeOnDelete();
            $table->text('question_text');
            $table->text('option_a')->nullable();
            $table->text('option_b')->nullable();
            $table->text('option_c')->nullable();
            $table->text('option_d')->nullable();
            $table->text('correct_answer')->nullable();
            $table->unsignedInteger('marks');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_paper_items');
    }
};
