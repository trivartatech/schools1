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
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            
            // e.g. 'student', 'staff', 'guardian'
            $table->string('entity_type', 50);
            
            // e.g. 'blood_group'
            $table->string('name', 100);
            
            // e.g. 'Blood Group'
            $table->string('label', 200);
            
            // 'text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio'
            $table->string('type', 50);
            
            // JSON array for 'select' or 'radio' options
            $table->json('options')->nullable();
            
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Ensuring that the field name is unique per entity type per school
            $table->unique(['school_id', 'entity_type', 'name']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            
            // Polymorphic relation to the entity (e.g. Student model, Staff model)
            $table->morphs('model');
            
            $table->text('value')->nullable();
            
            $table->timestamps();
            
            // A model should only have one value entry per custom field
            $table->unique(['custom_field_id', 'model_id', 'model_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
    }
};
