<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->date('snapshot_date');
            $table->date('range_from')->nullable();
            $table->date('range_to')->nullable();
            $table->json('snapshot_json');
            $table->json('insights_json');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['school_id', 'generated_at']);
            $table->index(['school_id', 'snapshot_date']);
        });

        Schema::create('ai_insight_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('filters_json');
            $table->timestamps();

            $table->index(['school_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insight_views');
        Schema::dropIfExists('ai_insights');
    }
};
