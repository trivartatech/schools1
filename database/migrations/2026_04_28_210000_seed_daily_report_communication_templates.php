<?php

use App\Models\CommunicationTemplate;
use App\Models\School;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-runs the system communication template seeder for every school so that
 * the newly added `daily_report` SMS + WhatsApp templates are inserted on
 * deploy. seedSystemTemplatesForSchool() is idempotent (uses firstOrCreate)
 * so existing rows are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        School::query()->each(function ($school) {
            CommunicationTemplate::seedSystemTemplatesForSchool($school->id);
        });
    }

    public function down(): void
    {
        // No-op: system templates are protected from deletion at the controller layer.
    }
};
