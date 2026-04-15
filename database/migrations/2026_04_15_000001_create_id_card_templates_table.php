<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_card_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->enum('orientation', ['landscape', 'portrait'])->default('landscape');
            $table->json('background');   // { type: 'color'|'image', value: '#hex'|'/storage/...' }
            $table->json('elements');     // array of element objects with x/y/w/h/style
            $table->unsignedTinyInteger('columns')->default(2); // default print columns per A4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_card_templates');
    }
};
