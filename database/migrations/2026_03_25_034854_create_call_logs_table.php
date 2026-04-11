<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('caller_name');
            $table->string('phone_number', 25);
            $table->enum('call_type', ['Incoming', 'Outgoing']);
            $table->enum('purpose', ['Enquiry', 'Complaint', 'Follow-up', 'Admission', 'Other']);
            $table->foreignId('handled_by_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('related_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('follow_up_date')->nullable();
            $table->boolean('follow_up_completed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
