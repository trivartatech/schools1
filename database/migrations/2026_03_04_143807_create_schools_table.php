<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trust_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->unique()->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->enum('board', ['CBSE', 'ICSE', 'State'])->default('CBSE');
            $table->string('affiliation_no')->nullable();
            $table->string('udise_code')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('principal_name')->nullable();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->string('currency')->default('INR');
            $table->string('language')->default('en');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->json('features')->nullable();  // feature flags per school
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
