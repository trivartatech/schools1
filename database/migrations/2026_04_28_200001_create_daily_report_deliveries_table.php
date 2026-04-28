<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_report_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_contact_id')->nullable()->constrained('admin_contacts')->nullOnDelete();

            $table->date('report_date');
            $table->enum('mode', ['daily', 'weekly'])->default('daily');
            $table->enum('channel_used', ['whatsapp', 'sms', 'failed']);
            $table->string('to_number', 30)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->string('pdf_path', 255)->nullable();

            $table->timestamps();

            $table->index(['school_id', 'report_date']);
            $table->index(['school_id', 'admin_contact_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_deliveries');
    }
};
