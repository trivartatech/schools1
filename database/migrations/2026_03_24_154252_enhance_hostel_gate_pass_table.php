<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hostel_leave_requests', function (Blueprint $table) {
            // Destination & escort details
            $table->string('destination')->nullable()->after('reason');
            $table->string('escort_name')->nullable()->after('destination');
            $table->string('escort_relation')->nullable()->after('escort_name');
            $table->string('escort_phone')->nullable()->after('escort_relation');
            $table->string('escort_id_proof_type')->nullable()->after('escort_phone');
            $table->string('escort_id_proof_photo')->nullable()->after('escort_id_proof_type');

            // Parent approval
            $table->enum('parent_approval', ['Pending', 'Approved', 'Rejected'])->default('Pending')->after('status');
            $table->string('parent_name')->nullable()->after('parent_approval');
            $table->string('parent_otp')->nullable()->after('parent_name');
            $table->boolean('parent_otp_verified')->default(false)->after('parent_otp');
            $table->timestamp('parent_approved_at')->nullable()->after('parent_otp_verified');

            // Gate security
            $table->string('student_exit_photo')->nullable()->after('actual_out_time');
            $table->string('escort_exit_photo')->nullable()->after('student_exit_photo');
            $table->string('student_return_photo')->nullable()->after('actual_in_time');

            // QR pass
            $table->string('pass_token')->nullable()->unique()->after('id');
            $table->boolean('is_expired')->default(false)->after('pass_token');
        });
    }

    public function down(): void
    {
        Schema::table('hostel_leave_requests', function (Blueprint $table) {
            $table->dropColumn([
                'destination', 'escort_name', 'escort_relation', 'escort_phone',
                'escort_id_proof_type', 'escort_id_proof_photo',
                'parent_approval', 'parent_name', 'parent_otp', 'parent_otp_verified', 'parent_approved_at',
                'student_exit_photo', 'escort_exit_photo', 'student_return_photo',
                'pass_token', 'is_expired'
            ]);
        });
    }
};
