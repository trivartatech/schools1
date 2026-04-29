<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_print_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('paper_size', 8)->default('A4'); // A4 | A5 | A6
            $table->unsignedTinyInteger('copies')->default(1); // 1..4

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_print_settings');
    }
};
