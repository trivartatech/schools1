<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_roll_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('hostel_id');
            $table->unsignedBigInteger('student_id');
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'leave', 'medical'])->default('present');
            $table->enum('slot', ['night', 'morning'])->default('night');
            $table->string('remarks', 500)->nullable();
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'hostel_id', 'student_id', 'date', 'slot'], 'hostel_roll_unique');
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('hostel_id')->references('id')->on('hostels')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('marked_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_roll_calls');
    }
};
