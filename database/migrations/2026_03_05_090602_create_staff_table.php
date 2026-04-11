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
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            
            // Employment Details
            $table->string('employee_id')->nullable();
            $table->string('qualification')->nullable();
            $table->integer('experience_years')->default(0);
            $table->date('joining_date')->nullable();
            
            // Financial & Statutory
            $table->decimal('basic_salary', 10, 2)->default(0);
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('pan_no')->nullable();
            $table->string('epf_no')->nullable();
            
            $table->string('status')->default('active'); // active, on_leave, resigned, terminated
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
