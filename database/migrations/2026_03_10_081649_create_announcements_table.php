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
        Schema::dropIfExists('voice_announcements');

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->enum('delivery_method', ['voice', 'sms', 'whatsapp']);
            $table->enum('audience_type', ['school', 'class', 'section', 'employee', 'individual']);
            $table->json('audience_ids')->nullable();
            $table->foreignId('communication_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audio_path')->nullable();
            $table->boolean('is_broadcasted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
