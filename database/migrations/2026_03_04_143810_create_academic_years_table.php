<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');           // e.g. "2025-26"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->enum('status', ['draft', 'active', 'frozen'])->default('draft');
            $table->foreignId('copied_from_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->json('copied_items')->nullable(); // which items were copied
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
