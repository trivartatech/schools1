<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('name');                    // e.g. "Casual Leave"
            $table->string('code', 10);               // e.g. "CL"
            $table->integer('days_allowed')->default(0); // Annual allocation (0 = unlimited)
            $table->string('color', 7)->default('#1169cd'); // Hex color for UI
            $table->boolean('is_paid')->default(true);
            $table->boolean('carry_forward')->default(false);
            $table->integer('max_carry_forward_days')->default(0);
            $table->boolean('requires_document')->default(false);
            $table->integer('min_notice_days')->default(0); // Advance notice required
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['school_id', 'code']); // Code must be unique per school
        });

        // Add leave_type_id FK to leaves table
        Schema::table('leaves', function (Blueprint $table) {
            $table->foreignId('leave_type_id')
                ->nullable()
                ->after('leave_type')
                ->constrained('leave_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['leave_type_id']);
            $table->dropColumn('leave_type_id');
        });
        Schema::dropIfExists('leave_types');
    }
};
