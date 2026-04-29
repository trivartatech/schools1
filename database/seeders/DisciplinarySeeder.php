<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DisciplinarySeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        $adminUserId = DB::table('users')->where('school_id', $schoolId)
            ->whereIn('user_type', ['principal', 'admin', 'school_admin'])
            ->value('id');

        Schema::disableForeignKeyConstraints();
        DB::table('disciplinary_records')->where('school_id', $schoolId)->delete();
        DB::table('disciplinary_categories')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // ── 1. Categories ─────────────────────────────────────────────────────
        $categories = [
            ['name' => 'Misconduct',           'short_code' => 'MISC',  'sort_order' => 1],
            ['name' => 'Bullying',             'short_code' => 'BULLY', 'sort_order' => 2],
            ['name' => 'Damage to Property',   'short_code' => 'DAMG',  'sort_order' => 3],
            ['name' => 'Dress Code Violation', 'short_code' => 'DRESS', 'sort_order' => 4],
            ['name' => 'Absenteeism',          'short_code' => 'ABSN',  'sort_order' => 5],
            ['name' => 'Cheating',             'short_code' => 'CHEAT', 'sort_order' => 6],
            ['name' => 'Disrespect',           'short_code' => 'DISR',  'sort_order' => 7],
            ['name' => 'Violence',             'short_code' => 'VIOL',  'sort_order' => 8],
            ['name' => 'Other',                'short_code' => 'OTH',   'sort_order' => 9],
        ];
        foreach ($categories as $c) {
            DB::table('disciplinary_categories')->insert([
                'school_id'  => $schoolId,
                'name'       => $c['name'],
                'short_code' => $c['short_code'],
                'sort_order' => $c['sort_order'],
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── 2. Sample records (~10) ───────────────────────────────────────────
        $studentIds = DB::table('students')->where('school_id', $schoolId)->pluck('id')->toArray();
        if (empty($studentIds)) {
            $this->command->info('DisciplinarySeeder: no students; only categories seeded.');
            return;
        }

        $sampleIncidents = [
            ['cat' => 'Misconduct',           'sev' => 'minor',    'desc' => 'Disrupted class with loud talking during lecture.',         'action' => 'Verbal warning given.',                          'consequence' => 'warning'],
            ['cat' => 'Dress Code Violation', 'sev' => 'minor',    'desc' => 'Came to school without proper uniform on Monday.',          'action' => 'Reminded of uniform policy.',                    'consequence' => 'warning'],
            ['cat' => 'Bullying',             'sev' => 'major',    'desc' => 'Reported for bullying junior student in playground.',       'action' => 'Parents called; counselling session arranged.', 'consequence' => 'parent_call'],
            ['cat' => 'Cheating',             'sev' => 'major',    'desc' => 'Caught using cheat sheet during midterm Maths exam.',       'action' => 'Exam zero awarded; parent notified.',            'consequence' => 'parent_call'],
            ['cat' => 'Absenteeism',          'sev' => 'moderate', 'desc' => 'Absent without informing for 5 consecutive days.',          'action' => 'Counselling and academic recovery plan.',         'consequence' => 'parent_call'],
            ['cat' => 'Damage to Property',   'sev' => 'moderate', 'desc' => 'Broke a classroom window during break time.',               'action' => 'Parents to pay damage cost.',                    'consequence' => 'parent_call'],
            ['cat' => 'Disrespect',           'sev' => 'minor',    'desc' => 'Argued disrespectfully with subject teacher.',              'action' => 'Apology submitted to teacher.',                  'consequence' => 'warning'],
            ['cat' => 'Misconduct',           'sev' => 'minor',    'desc' => 'Used mobile phone during class.',                            'action' => 'Phone confiscated; returned at day end.',         'consequence' => 'warning'],
            ['cat' => 'Violence',             'sev' => 'major',    'desc' => 'Got into a physical fight with classmate.',                 'action' => '3-day suspension; mediation session.',           'consequence' => 'suspension'],
            ['cat' => 'Other',                'sev' => 'minor',    'desc' => 'Threw food in cafeteria.',                                   'action' => 'Cleanup duty for one week.',                     'consequence' => 'detention'],
        ];

        foreach ($sampleIncidents as $i => $inc) {
            $studentId    = $studentIds[array_rand($studentIds)];
            $incidentDate = $now->copy()->subDays(rand(5, 90));
            $isResolved   = rand(0, 100) < 70;

            DB::table('disciplinary_records')->insert([
                'school_id'           => $schoolId,
                'student_id'          => $studentId,
                'reported_by'         => $adminUserId,
                'reviewed_by'         => $isResolved ? $adminUserId : null,
                'incident_date'       => $incidentDate->format('Y-m-d'),
                'category'            => $inc['cat'],
                'severity'            => $inc['sev'],
                'description'         => $inc['desc'],
                'action_taken'        => $inc['action'],
                'status'              => $isResolved ? 'resolved' : (rand(0, 1) ? 'under_review' : 'open'),
                'consequence'         => $inc['consequence'],
                'consequence_from'    => $inc['consequence'] === 'suspension' ? $incidentDate->copy()->addDays(1)->format('Y-m-d') : null,
                'consequence_to'      => $inc['consequence'] === 'suspension' ? $incidentDate->copy()->addDays(3)->format('Y-m-d') : null,
                'parent_notified'     => in_array($inc['consequence'], ['parent_call', 'suspension', 'expulsion']),
                'parent_notified_at'  => in_array($inc['consequence'], ['parent_call', 'suspension', 'expulsion']) ? $incidentDate->format('Y-m-d') : null,
                'student_statement'   => null,
                'notes'               => null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }

        $this->command->info('✅ Disciplinary seeded: 9 categories, 10 sample records.');
    }
}
