<?php

namespace Database\Seeders;

use App\Models\CommunicationTemplate;
use App\Models\School;
use Illuminate\Database\Seeder;

class CommunicationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        School::query()->each(function ($school) {
            CommunicationTemplate::seedSystemTemplatesForSchool($school->id);
        });
    }
}
