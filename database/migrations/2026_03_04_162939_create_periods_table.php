<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);           // e.g., "Period 1", "Lunch Break"
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('type', ['period', 'break', 'lunch', 'assembly'])->default('period');
            $table->boolean('is_weekend')->default(false);
            $table->unsignedTinyInteger('order')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
