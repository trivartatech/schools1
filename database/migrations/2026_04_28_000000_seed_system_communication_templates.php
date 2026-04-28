<?php

use App\Models\CommunicationTemplate;
use App\Models\School;
use Illuminate\Database\Migrations\Migration;

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
