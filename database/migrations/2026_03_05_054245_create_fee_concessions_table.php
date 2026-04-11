<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_concessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->string('name');                            // e.g. "Sibling Discount", "Merit Scholarship"
            $table->text('description')->nullable();           // reason / notes
            $table->enum('type', ['percentage', 'fixed']);     // % of fee or flat ₹ amount
            $table->decimal('value', 10, 2);                  // e.g. 25 (%) or 5000 (₹)

            // Optional: restrict to specific fee heads (JSON array of fee_head IDs); null = applies to all
            $table->json('applicable_fee_heads')->nullable();

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'student_id', 'academic_year_id']);
        });

        // Track which concession was applied to each payment
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->foreignId('concession_id')
                  ->nullable()
                  ->after('discount')
                  ->constrained('fee_concessions')
                  ->nullOnDelete();
            $table->string('concession_note')->nullable()->after('concession_id');
        });
    }

    public function down(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\FeeConcession::class);
            $table->dropColumn(['concession_id', 'concession_note']);
        });
        Schema::dropIfExists('fee_concessions');
    }
};
