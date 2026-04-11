<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->unsignedSmallInteger('sla_hours')->default(24)->after('resolution_notes');
            $table->timestamp('escalated_at')->nullable()->after('sla_hours');
            $table->unsignedTinyInteger('escalation_level')->default(0)->after('escalated_at');
            $table->boolean('sla_breached')->default(false)->after('escalation_level');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn(['sla_hours', 'escalated_at', 'escalation_level', 'sla_breached']);
        });
    }
};
