<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_complaints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('hostel_id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('reported_by');
            $table->enum('category', ['maintenance', 'electrical', 'plumbing', 'furniture', 'cleanliness', 'pest_control', 'other'])->default('maintenance');
            $table->string('title');
            $table->text('description');
            $table->string('location', 255)->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed', 'rejected'])->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('hostel_id')->references('id')->on('hostels')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
            $table->foreign('reported_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_complaints');
    }
};
