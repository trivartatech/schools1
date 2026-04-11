<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            // Certificate identity
            $table->string('certificate_no', 50)->nullable();   // set on issue

            // Workflow
            $table->enum('status', ['requested', 'approved', 'issued', 'rejected'])->default('requested');

            // TC content fields
            $table->date('leaving_date');
            $table->text('reason')->nullable();
            $table->string('conduct', 50)->default('Good');     // Good / Satisfactory / Poor
            $table->string('last_class_studied')->nullable();   // e.g. "10th - A"
            $table->date('fee_paid_upto')->nullable();
            $table->boolean('has_dues')->default(false);

            // Remarks from approver/issuer
            $table->text('remarks')->nullable();

            // Audit trail
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->unique(['school_id', 'certificate_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_certificates');
    }
};
