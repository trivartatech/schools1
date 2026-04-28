<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplinary_categories', function (Blueprint $table) {
            $table->string('short_code', 20)->nullable()->after('name');
        });

        $defaults = [
            'Misconduct'           => 'MISC',
            'Bullying'             => 'BULLY',
            'Damage to Property'   => 'DAMG',
            'Dress Code Violation' => 'DRESS',
            'Absenteeism'          => 'ABSN',
            'Cheating'             => 'CHEAT',
            'Disrespect'           => 'DISR',
            'Violence'             => 'VIOL',
            'Other'                => 'OTH',
        ];

        DB::table('disciplinary_categories')->whereNull('short_code')->orderBy('id')
            ->each(function ($row) use ($defaults) {
                $base = $defaults[$row->name] ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $row->name) ?: 'CAT', 0, 5));
                $code = $base;
                $i = 1;
                while (DB::table('disciplinary_categories')
                    ->where('school_id', $row->school_id)
                    ->where('short_code', $code)
                    ->exists()) {
                    $code = substr($base, 0, 18) . $i++;
                }
                DB::table('disciplinary_categories')->where('id', $row->id)->update(['short_code' => $code]);
            });

        Schema::table('disciplinary_categories', function (Blueprint $table) {
            $table->unique(['school_id', 'short_code'], 'disc_cat_school_shortcode_unique');
        });
    }

    public function down(): void
    {
        Schema::table('disciplinary_categories', function (Blueprint $table) {
            $table->dropUnique('disc_cat_school_shortcode_unique');
            $table->dropColumn('short_code');
        });
    }
};
