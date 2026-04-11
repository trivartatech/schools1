<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('father_phone')->nullable();
            $table->string('mother_phone')->nullable();
            $table->string('primary_phone'); // Used for matching siblings
            $table->string('father_occupation')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['school_id', 'primary_phone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parents');
    }
};
