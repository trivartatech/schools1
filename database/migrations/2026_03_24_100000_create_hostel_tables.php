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
        Schema::create('hostels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['Boys', 'Girls', 'Co-ed']);
            $table->string('address')->nullable();
            $table->integer('intake_capacity')->default(0);
            $table->text('description')->nullable();
            $table->foreignId('warden_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hostel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->string('block_name')->nullable();
            $table->string('floor_name')->nullable();
            $table->string('room_number');
            $table->integer('capacity')->default(1);
            $table->string('room_type')->nullable(); // AC, Non-AC, etc.
            $table->decimal('cost_per_month', 10, 2)->default(0);
            $table->enum('status', ['Available', 'Full', 'Maintenance'])->default('Available');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hostel_beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_room_id')->constrained('hostel_rooms')->cascadeOnDelete();
            $table->string('name'); // e.g., 'Bed 1'
            $table->enum('status', ['Available', 'Occupied', 'Maintenance'])->default('Available');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hostel_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('hostel_bed_id')->nullable()->constrained('hostel_beds')->nullOnDelete();
            $table->date('admission_date')->nullable();
            $table->date('vacate_date')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_relation')->nullable();
            $table->string('id_proof')->nullable(); // Document path
            $table->text('medical_info')->nullable();
            $table->enum('mess_type', ['Veg', 'Non-Veg', 'Custom', 'None'])->default('None');
            $table->enum('status', ['Active', 'Vacated', 'Suspended'])->default('Active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hostel_visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('visitor_name');
            $table->string('relation')->nullable();
            $table->string('phone')->nullable();
            $table->date('date');
            $table->time('in_time');
            $table->time('out_time')->nullable();
            $table->string('purpose')->nullable();
            $table->string('id_proof')->nullable();
            $table->string('otp')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hostel_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('leave_type', ['Day Out', 'Night Out', 'Home Time', 'Emergency']);
            $table->dateTime('from_date');
            $table->dateTime('to_date');
            $table->text('reason');
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Out', 'Returned'])->default('Pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('actual_out_time')->nullable();
            $table->dateTime('actual_in_time')->nullable();
            $table->text('late_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        
        Schema::create('hostel_mess_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->string('day'); // Monday, Tuesday, etc.
            $table->string('meal_type'); // Breakfast, Lunch, Snacks, Dinner
            $table->text('items');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('fee_heads', function (Blueprint $table) {
            if (!Schema::hasColumn('fee_heads', 'is_hostel_fee')) {
                $table->boolean('is_hostel_fee')->default(false)->after('sort_order');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_heads', function (Blueprint $table) {
            $table->dropColumn('is_hostel_fee');
        });
        
        Schema::dropIfExists('hostel_mess_menus');
        Schema::dropIfExists('hostel_leave_requests');
        Schema::dropIfExists('hostel_visitors');
        Schema::dropIfExists('hostel_students');
        Schema::dropIfExists('hostel_beds');
        Schema::dropIfExists('hostel_rooms');
        Schema::dropIfExists('hostels');
    }
};
