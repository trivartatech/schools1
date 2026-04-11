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
        Schema::table('hostel_visitors', function (Blueprint $table) {
            // $table->unsignedBigInteger('student_id')->nullable()->change();
            $table->string('visitor_type')->nullable(); 
            $table->string('meet_user_type')->default('Student'); 
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->integer('visitor_count')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hostel_visitors', function (Blueprint $table) {
            $table->dropColumn(['visitor_type', 'meet_user_type', 'staff_id', 'visitor_count']);
        });
    }
};
