<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an explicit `sort_order` column to exam_terms and exam_types so
 * admins can rank entries from the Exam Terms / Exam Types pages
 * independently of their database id.
 *
 * Existing rows are back-filled by id order (per-school, per-year for
 * terms; per-term for types) so the current display order is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_terms', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('display_name')->index();
        });

        Schema::table('exam_types', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('display_name')->index();
        });

        // ── Back-fill existing rows ─────────────────────────────────────
        // Terms: rank by id within (school_id, academic_year_id).
        $terms = DB::table('exam_terms')
            ->orderBy('school_id')->orderBy('academic_year_id')->orderBy('id')
            ->get(['id', 'school_id', 'academic_year_id']);

        $rank = [];
        foreach ($terms as $row) {
            $key = $row->school_id . ':' . $row->academic_year_id;
            $rank[$key] = ($rank[$key] ?? 0) + 1;
            DB::table('exam_terms')->where('id', $row->id)->update(['sort_order' => $rank[$key]]);
        }

        // Types: rank by id within exam_term_id.
        $types = DB::table('exam_types')
            ->orderBy('exam_term_id')->orderBy('id')
            ->get(['id', 'exam_term_id']);

        $rank = [];
        foreach ($types as $row) {
            $key = (string) $row->exam_term_id;
            $rank[$key] = ($rank[$key] ?? 0) + 1;
            DB::table('exam_types')->where('id', $row->id)->update(['sort_order' => $rank[$key]]);
        }
    }

    public function down(): void
    {
        Schema::table('exam_terms', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
        Schema::table('exam_types', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
