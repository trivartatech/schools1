<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create a pivot linking sections to academic years so each section can be
     * activated/deactivated per year without hard-deleting the row (which would
     * cascade into historical enrollments). Backfills from the distinct
     * (section_id, academic_year_id) pairs already recorded in student
     * academic histories.
     */
    public function up(): void
    {
        Schema::create('section_academic_year', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['section_id', 'academic_year_id']);
        });

        // Backfill from history: every (section, year) pair ever used
        $historyPairs = DB::table('student_academic_histories')
            ->whereNotNull('section_id')
            ->whereNotNull('academic_year_id')
            ->select('section_id', 'academic_year_id')
            ->distinct()
            ->get();

        $now = now();
        $rows = [];
        foreach ($historyPairs as $p) {
            $rows[] = [
                'section_id'       => $p->section_id,
                'academic_year_id' => $p->academic_year_id,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        if (!empty($rows)) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('section_academic_year')->insertOrIgnore($chunk);
            }
        }

        // Any section that has no pivot entry after backfill (e.g. just-created
        // empty sections) gets attached to the current academic year so it
        // remains visible in the current year's dropdowns.
        $currentYear = DB::table('academic_years')->where('is_current', 1)->first();
        if ($currentYear) {
            $orphanSections = DB::table('sections')
                ->leftJoin('section_academic_year as say', 'say.section_id', '=', 'sections.id')
                ->whereNull('say.id')
                ->whereNull('sections.deleted_at')
                ->pluck('sections.id');

            $orphanRows = $orphanSections->map(fn($sid) => [
                'section_id'       => $sid,
                'academic_year_id' => $currentYear->id,
                'created_at'       => $now,
                'updated_at'       => $now,
            ])->all();

            if (!empty($orphanRows)) {
                foreach (array_chunk($orphanRows, 500) as $chunk) {
                    DB::table('section_academic_year')->insertOrIgnore($chunk);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('section_academic_year');
    }
};
