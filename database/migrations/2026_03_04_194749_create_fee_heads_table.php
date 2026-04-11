<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');           // e.g. "Tuition Fee", "Lab Fee", "Bus Fee"
            $table->string('short_code')->nullable(); // e.g. TF, LF
            $table->text('description')->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->decimal('gst_percent', 5, 2)->default(0.00);
            $table->integer('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_heads');
    }
};
