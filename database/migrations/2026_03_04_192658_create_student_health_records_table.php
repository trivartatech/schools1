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
        Schema::create('student_health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete()->unique();

            // Physical stats
            $table->decimal('height_cm', 5, 1)->nullable();       // in cm
            $table->decimal('weight_kg', 5, 1)->nullable();       // in kg
            $table->string('vision_left')->nullable();             // e.g. 6/6
            $table->string('vision_right')->nullable();
            $table->string('hearing')->nullable();                 // Normal / Impaired

            // Medical info
            $table->text('known_allergies')->nullable();
            $table->text('chronic_conditions')->nullable();        // asthma, diabetes etc.
            $table->text('current_medications')->nullable();
            $table->text('past_surgeries')->nullable();
            $table->string('disability')->nullable();              // None / Physical / Learning etc.
            $table->string('special_needs')->nullable();

            // Vaccinations (JSON: [{name, date, dose, notes}])
            $table->json('vaccinations')->nullable();

            // Emergency medical contact (could differ from guardian)
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relation')->nullable();

            // Doctor
            $table->string('family_doctor_name')->nullable();
            $table->string('family_doctor_phone')->nullable();

            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_health_records');
    }
};
