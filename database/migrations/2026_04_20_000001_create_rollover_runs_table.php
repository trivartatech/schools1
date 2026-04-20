<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollover_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('target_year_id')->constrained('academic_years')->cascadeOnDelete();

            $table->enum('state', [
                'draft',
                'structure_running', 'structure_done',
                'students_running',  'students_done',
                'fees_running',      'fees_done',
                'finalized', 'failed', 'cancelled',
            ])->default('draft');

            $table->json('config')->nullable();
            $table->json('stats')->nullable();
            $table->text('error')->nullable();

            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'state']);
            $table->index(['source_year_id', 'target_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rollover_runs');
    }
};
