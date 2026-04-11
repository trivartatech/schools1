<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StaffAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;

        $adminUserId = DB::table('users')->where('school_id', $schoolId)->whereIn('user_type', ['principal', 'admin'])->value('id');
        $staffIds    = DB::table('staff')->where('school_id', $schoolId)->pluck('id')->toArray();

        if (empty($staffIds)) {
            $this->command->warn('No staff found — skipping StaffAttendanceSeeder.');
            return;
        }

        DB::table('staff_attendances')->where('school_id', $schoolId)->delete();

        $statuses    = ['present', 'present', 'present', 'present', 'absent', 'late', 'half_day', 'leave'];
        $checkInBase = ['08:30', '08:45', '09:00', '09:10', '09:20'];
        $checkOutBase= ['16:30', '16:45', '17:00', '17:15', '17:30'];

        // Seed last 60 working days
        $startDate = Carbon::now()->subDays(60);
        $endDate   = Carbon::now();
        $inserted  = 0;

        $batch = [];
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            if ($current->isWeekend()) {
                $current->addDay();
                continue;
            }

            foreach ($staffIds as $idx => $staffId) {
                $status   = $statuses[($idx + (int)$current->format('d')) % count($statuses)];
                $checkIn  = null;
                $checkOut = null;

                if (in_array($status, ['present', 'late', 'half_day'])) {
                    $checkIn  = $status === 'late'
                        ? '09:' . rand(30, 59) . ':00'
                        : $checkInBase[$idx % count($checkInBase)] . ':00';
                    $checkOut = $status === 'half_day'
                        ? '13:00:00'
                        : $checkOutBase[$idx % count($checkOutBase)] . ':00';
                }

                $batch[] = [
                    'school_id' => $schoolId,
                    'staff_id'  => $staffId,
                    'date'      => $current->format('Y-m-d'),
                    'status'    => $status,
                    'check_in'  => $checkIn,
                    'check_out' => $checkOut,
                    'remarks'   => $status === 'absent' ? 'Unplanned absence' : ($status === 'late' ? 'Traffic delay' : null),
                    'marked_by' => $adminUserId,
                    'created_at'=> $current->format('Y-m-d H:i:s'),
                    'updated_at'=> $current->format('Y-m-d H:i:s'),
                ];

                if (count($batch) >= 500) {
                    DB::table('staff_attendances')->insert($batch);
                    $inserted += count($batch);
                    $batch = [];
                }
            }
            $current->addDay();
        }

        if (!empty($batch)) {
            DB::table('staff_attendances')->insert($batch);
            $inserted += count($batch);
        }

        $this->command->info('✅ Staff Attendance seeded!');
        $this->command->info("   - {$inserted} records for " . count($staffIds) . ' staff over 60 days');
    }
}
