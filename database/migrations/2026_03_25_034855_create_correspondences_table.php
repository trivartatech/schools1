<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['Incoming', 'Outgoing']);
            $table->string('reference_number')->nullable();
            $table->string('sender_receiver_name');
            $table->string('subject');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('attachment_path')->nullable();
            $table->string('dispatch_tracking', 100)->nullable();
            $table->string('courier_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondences');
    }
};
