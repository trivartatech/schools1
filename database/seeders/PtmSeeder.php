<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class PtmSeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        Schema::disableForeignKeyConstraints();
        DB::table('ptm_bookings')->whereIn('slot_id',
            DB::table('ptm_slots')->whereIn('session_id',
                DB::table('ptm_sessions')->where('school_id', $schoolId)->pluck('id')
            )->pluck('id')
        )->delete();
        DB::table('ptm_slots')->whereIn('session_id',
            DB::table('ptm_sessions')->where('school_id', $schoolId)->pluck('id')
        )->delete();
        DB::table('ptm_sessions')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        $staffIds = DB::table('staff')->where('school_id', $schoolId)->limit(8)->pluck('id')->toArray();
        if (empty($staffIds)) {
            $this->command->info('PtmSeeder: no staff present; skipping.');
            return;
        }

        $studentIds = DB::table('students')->where('school_id', $schoolId)->pluck('id')->toArray();

        // ── 1. Two PTM sessions: one past (closed), one upcoming (open) ───────
        $sessionsData = [
            ['title' => 'Quarterly PTM — Q1', 'date' => $now->copy()->subDays(45)->format('Y-m-d'), 'status' => 'closed'],
            ['title' => 'Mid-term PTM',        'date' => $now->copy()->addDays(20)->format('Y-m-d'), 'status' => 'open'],
        ];

        foreach ($sessionsData as $s) {
            $sessionId = DB::table('ptm_sessions')->insertGetId([
                'school_id'              => $schoolId,
                'title'                  => $s['title'],
                'date'                   => $s['date'],
                'start_time'             => '09:00:00',
                'end_time'               => '13:00:00',
                'slot_duration_minutes'  => 15,
                'description'            => 'Discussion of student progress with parents.',
                'status'                 => $s['status'],
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);

            // ── 2. Slots: every 15 min from 9:00 to 13:00 = 16 slots/staff ──
            $slotIds = [];
            $startMinutes = 9 * 60;
            $endMinutes   = 13 * 60;
            foreach ($staffIds as $sid) {
                for ($t = $startMinutes; $t < $endMinutes; $t += 15) {
                    $h = floor($t / 60);
                    $m = $t % 60;
                    $time = sprintf('%02d:%02d:00', $h, $m);
                    $slotIds[] = [
                        'session_id' => $sessionId,
                        'staff_id'   => $sid,
                        'slot_time'  => $time,
                    ];
                }
            }
            // Limit to ~30 slots for a sensible demo (or insert all and book a fraction)
            // Insert all but only book a subset.
            $allSlotRows = array_map(fn($s) => array_merge($s, [
                'is_booked'  => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]), $slotIds);
            foreach (array_chunk($allSlotRows, 200) as $chunk) {
                DB::table('ptm_slots')->insert($chunk);
            }

            // ── 3. Bookings: book ~15 random slots for this session ──
            $sessionSlots = DB::table('ptm_slots')->where('session_id', $sessionId)->limit(15)->pluck('id')->toArray();
            foreach ($sessionSlots as $slotId) {
                if (empty($studentIds)) break;
                $studentId = $studentIds[array_rand($studentIds)];
                $status = $s['status'] === 'closed'
                    ? (['completed', 'completed', 'no_show'][array_rand(['completed', 'completed', 'no_show'])])
                    : 'booked';
                $notes = $status === 'completed' ? 'Discussed academic performance and improvement areas.' : null;

                DB::table('ptm_bookings')->insertOrIgnore([
                    'slot_id'         => $slotId,
                    'student_id'      => $studentId,
                    'parent_user_id'  => null,
                    'status'          => $status,
                    'meeting_notes'   => $notes,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
                DB::table('ptm_slots')->where('id', $slotId)->update(['is_booked' => true]);
            }
        }

        $this->command->info('✅ PTM seeded: 2 sessions, ~' . (count($staffIds) * 16 * 2) . ' slots, ~30 bookings.');
    }
}
