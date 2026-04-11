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
        Schema::create('communication_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->string('type'); // sms, whatsapp, mail, push
            $table->string('name'); // e.g. Attendance Absent
            $table->string('slug')->index(); // e.g. attendance_absent
            $table->string('template_id')->nullable(); // external provider id
            $table->string('subject')->nullable(); // mostly for mail
            $table->text('content')->nullable(); // supports placeholders like ##NAME##
            $table->boolean('is_active')->default(true);
            $table->json('variables')->nullable(); // allowed placeholders
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_templates');
    }
};
