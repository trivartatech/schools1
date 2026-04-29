<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class HouseSeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        $academicYearId = DB::table('academic_years')->where('school_id', $schoolId)->where('is_current', true)->value('id')
                       ?? DB::table('academic_years')->where('school_id', $schoolId)->where('status', 'active')->value('id');

        if (!$academicYearId) {
            $this->command->error('HouseSeeder: no active academic year found.');
            return;
        }

        $adminUserId = DB::table('users')->where('school_id', $schoolId)
            ->whereIn('user_type', ['principal', 'admin', 'school_admin'])
            ->value('id');

        Schema::disableForeignKeyConstraints();
        DB::table('house_points')->where('school_id', $schoolId)->delete();
        DB::table('house_students')->where('school_id', $schoolId)->delete();
        DB::table('houses')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // ── 1. Four houses (classic Indian school colours) ────────────────────
        $housesData = [
            ['name' => 'Aravalli', 'color' => '#dc2626'], // red
            ['name' => 'Nilgiri',  'color' => '#2563eb'], // blue
            ['name' => 'Shivalik', 'color' => '#16a34a'], // green
            ['name' => 'Vindhya',  'color' => '#eab308'], // yellow
        ];

        $teacherIds = DB::table('users')->where('school_id', $schoolId)
            ->where('user_type', 'teacher')->pluck('id')->toArray();

        $houseIds = [];
        foreach ($housesData as $i => $h) {
            $houseIds[] = DB::table('houses')->insertGetId([
                'school_id'           => $schoolId,
                'name'                => $h['name'],
                'color'               => $h['color'],
                'incharge_staff_id'   => $teacherIds[$i] ?? null,
                'captain_student_id'  => null, // set below
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }

        // ── 2. Distribute every student across the 4 houses ───────────────────
        $studentIds = DB::table('students')->where('school_id', $schoolId)->pluck('id')->toArray();

        if (empty($studentIds)) {
            $this->command->info('HouseSeeder: no students; houses seeded but no assignments.');
            return;
        }

        $rows = [];
        foreach ($studentIds as $idx => $sid) {
            $rows[] = [
                'school_id'        => $schoolId,
                'house_id'         => $houseIds[$idx % 4],
                'student_id'       => $sid,
                'academic_year_id' => $academicYearId,
                'assigned_by'      => $adminUserId,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }
        // Insert in chunks to keep query size sane
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('house_students')->insert($chunk);
        }

        // ── 3. Pick a captain per house (first student assigned to that house)
        foreach ($houseIds as $i => $hid) {
            $captainId = DB::table('house_students')->where('house_id', $hid)->value('student_id');
            if ($captainId) {
                DB::table('houses')->where('id', $hid)->update(['captain_student_id' => $captainId, 'updated_at' => $now]);
            }
        }

        // ── 4. Award some house points (~30 entries spread across houses) ─────
        $categories = ['sports', 'academic', 'cultural', 'discipline', 'general'];
        $reasons = [
            'sports'     => ['Won inter-house cricket match', 'First place in athletics meet', 'Football tournament champions', 'Excellence in basketball'],
            'academic'   => ['Top score in monthly test', 'Quiz competition winners', 'Best class average', 'Science fair gold medal'],
            'cultural'   => ['Annual day performance', 'Music competition', 'Dance championship', 'Art exhibition winners'],
            'discipline' => ['Best discipline award', 'Cleanliness drive winner', 'Punctuality recognition', 'Demerit for late submission'],
            'general'    => ['Community service contribution', 'Leadership recognition', 'Class participation', 'Helping new students'],
        ];

        $pointsRows = [];
        for ($i = 0; $i < 30; $i++) {
            $cat = $categories[array_rand($categories)];
            $isAward = rand(0, 100) < 85; // mostly positive
            $points  = $isAward ? rand(5, 50) : -rand(3, 15);

            $pointsRows[] = [
                'school_id'        => $schoolId,
                'house_id'         => $houseIds[array_rand($houseIds)],
                'academic_year_id' => $academicYearId,
                'category'         => $cat,
                'points'           => $points,
                'description'      => $reasons[$cat][array_rand($reasons[$cat])],
                'reference_type'   => null,
                'reference_id'     => null,
                'awarded_by'       => $adminUserId,
                'created_at'       => $now->copy()->subDays(rand(1, 60)),
                'updated_at'       => $now,
            ];
        }
        DB::table('house_points')->insert($pointsRows);

        $this->command->info('✅ Houses seeded: 4 houses, ' . count($studentIds) . ' student assignments, 30 point entries.');
    }
}
