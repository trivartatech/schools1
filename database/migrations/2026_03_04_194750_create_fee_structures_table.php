<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // fee_structures defines WHAT to charge per class/year/term
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('course_classes')->cascadeOnDelete();
            $table->foreignId('fee_head_id')->constrained()->cascadeOnDelete();

            $table->enum('term', ['annual', 'term1', 'term2', 'term3', 'monthly', 'quarterly', 'half_yearly'])->default('annual');
            $table->decimal('amount', 10, 2);
            $table->decimal('late_fee_per_day', 8, 2)->default(0);
            $table->date('due_date')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
