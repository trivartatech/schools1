<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('pass_type', ['Student', 'Visitor', 'Staff']);
            $table->nullableMorphs('user');
            $table->nullableMorphs('requested_by');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verification_method')->nullable();
            $table->string('picked_up_by_name')->nullable();
            $table->string('relationship')->nullable();
            $table->string('picker_photo_path')->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Exited', 'Returned'])->default('Pending');
            $table->string('qr_code_token', 100)->unique()->nullable();
            $table->dateTime('exit_time')->nullable();
            $table->dateTime('return_time')->nullable();
            $table->text('reason')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_passes');
    }
};
