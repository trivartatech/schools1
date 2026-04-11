<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add applicable_to to leave_types so types can be scoped ──────
        Schema::table('leave_types', function (Blueprint $table) {
            // 'staff' | 'student' | 'both'
            $table->enum('applicable_to', ['staff', 'student', 'both'])
                  ->default('staff')
                  ->after('sort_order');
        });

        // ── 2. Student Leave Applications ────────────────────────────────────
        Schema::create('student_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // Optional link to a formal leave type
            $table->foreignId('leave_type_id')->nullable()->constrained('leave_types')->nullOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');

            // Status flow: pending → approved | rejected
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // Who approved/rejected (a school staff user)
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            // Optional remarks from approver
            $table->text('remarks')->nullable();

            // Applied by: student themselves or parent
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexes for common query patterns
            $table->index(['school_id', 'student_id', 'status']);
            $table->index(['school_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_leaves');

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('applicable_to');
        });
    }
};
