<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class HostelSeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        $adminUserId = DB::table('users')->where('email', 'principal@dps.com')->value('id')
                    ?? DB::table('users')->where('school_id', $schoolId)->first()->id;

        // Clear existing hostel data
        Schema::disableForeignKeyConstraints();
        DB::table('hostel_complaints')->where('school_id', $schoolId)->delete();
        DB::table('hostel_leave_requests')->where('school_id', $schoolId)->delete();
        DB::table('hostel_visitors')->where('school_id', $schoolId)->delete();
        DB::table('hostel_mess_menus')->where('school_id', $schoolId)->delete();
        DB::table('hostel_students')->where('school_id', $schoolId)->delete();
        DB::table('hostel_beds')->where('school_id', $schoolId)->delete();
        DB::table('hostel_rooms')->where('school_id', $schoolId)->delete();
        DB::table('hostels')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // ── 1. Hostels ─────────────────────────────────────────────────────────
        $boysHostelId = DB::table('hostels')->insertGetId([
            'school_id'       => $schoolId,
            'name'            => 'Boys Hostel - Eklavya Bhavan',
            'type'            => 'Boys',
            'address'         => 'Block-B, School Campus, Dwarka, New Delhi',
            'intake_capacity' => 120,
            'description'     => 'Well-equipped hostel for boys with 24x7 security, WiFi, mess facility.',
            'warden_id'       => $adminUserId,
            'created_at'      => $now, 'updated_at' => $now,
        ]);

        $girlsHostelId = DB::table('hostels')->insertGetId([
            'school_id'       => $schoolId,
            'name'            => 'Girls Hostel - Saraswati Bhavan',
            'type'            => 'Girls',
            'address'         => 'Block-C, School Campus, Dwarka, New Delhi',
            'intake_capacity' => 80,
            'description'     => 'Girls-only hostel with lady warden, strict security, and separate mess.',
            'warden_id'       => $adminUserId,
            'created_at'      => $now, 'updated_at' => $now,
        ]);

        // ── 2. Rooms ───────────────────────────────────────────────────────────
        $roomsData = [
            // Boys hostel rooms (3 floors × 4 rooms)
            ['hostel_id' => $boysHostelId, 'block' => 'A', 'floor' => 'Ground Floor', 'room_number' => 'A-101', 'capacity' => 4, 'type' => 'Dormitory',   'cost' => 3500, 'status' => 'Full'],
            ['hostel_id' => $boysHostelId, 'block' => 'A', 'floor' => 'Ground Floor', 'room_number' => 'A-102', 'capacity' => 4, 'type' => 'Dormitory',   'cost' => 3500, 'status' => 'Available'],
            ['hostel_id' => $boysHostelId, 'block' => 'A', 'floor' => 'First Floor',  'room_number' => 'A-201', 'capacity' => 2, 'type' => 'Semi-Private', 'cost' => 5000, 'status' => 'Full'],
            ['hostel_id' => $boysHostelId, 'block' => 'A', 'floor' => 'First Floor',  'room_number' => 'A-202', 'capacity' => 2, 'type' => 'Semi-Private', 'cost' => 5000, 'status' => 'Available'],
            ['hostel_id' => $boysHostelId, 'block' => 'B', 'floor' => 'Ground Floor', 'room_number' => 'B-101', 'capacity' => 4, 'type' => 'Dormitory',   'cost' => 3500, 'status' => 'Full'],
            ['hostel_id' => $boysHostelId, 'block' => 'B', 'floor' => 'First Floor',  'room_number' => 'B-201', 'capacity' => 1, 'type' => 'Private',      'cost' => 8000, 'status' => 'Full'],
            // Girls hostel rooms
            ['hostel_id' => $girlsHostelId, 'block' => 'C', 'floor' => 'Ground Floor', 'room_number' => 'C-101', 'capacity' => 4, 'type' => 'Dormitory',   'cost' => 3500, 'status' => 'Full'],
            ['hostel_id' => $girlsHostelId, 'block' => 'C', 'floor' => 'Ground Floor', 'room_number' => 'C-102', 'capacity' => 4, 'type' => 'Dormitory',   'cost' => 3500, 'status' => 'Available'],
            ['hostel_id' => $girlsHostelId, 'block' => 'C', 'floor' => 'First Floor',  'room_number' => 'C-201', 'capacity' => 2, 'type' => 'Semi-Private', 'cost' => 5000, 'status' => 'Full'],
            ['hostel_id' => $girlsHostelId, 'block' => 'C', 'floor' => 'First Floor',  'room_number' => 'C-202', 'capacity' => 1, 'type' => 'Private',      'cost' => 8000, 'status' => 'Full'],
        ];

        $roomIds = [];
        foreach ($roomsData as $r) {
            $roomIds[] = DB::table('hostel_rooms')->insertGetId([
                'school_id'      => $schoolId,
                'hostel_id'      => $r['hostel_id'],
                'block_name'     => $r['block'],
                'floor_name'     => $r['floor'],
                'room_number'    => $r['room_number'],
                'capacity'       => $r['capacity'],
                'room_type'      => $r['type'],
                'cost_per_month' => $r['cost'],
                'status'         => $r['status'],
                'created_at'     => $now, 'updated_at' => $now,
            ]);
        }

        // ── 3. Beds ────────────────────────────────────────────────────────────
        $bedIds = []; // room_id => [bed_id, ...]
        foreach (array_keys($roomsData) as $i) {
            $roomId   = $roomIds[$i];
            $capacity = $roomsData[$i]['capacity'];
            $bedIds[$roomId] = [];
            for ($b = 1; $b <= $capacity; $b++) {
                $status = ($roomsData[$i]['status'] === 'Full') ? 'Occupied' : 'Available';
                $bedIds[$roomId][] = DB::table('hostel_beds')->insertGetId([
                    'school_id'     => $schoolId,
                    'hostel_room_id' => $roomId,
                    'name'          => 'Bed ' . $b,
                    'status'        => $status,
                    'created_at'    => $now, 'updated_at' => $now,
                ]);
            }
        }

        // ── 4. Hostel Students ─────────────────────────────────────────────────
        // Pick students from Class 9 and 10 (more realistic for hostel)
        $academicYearId = DB::table('academic_years')->where('school_id', $schoolId)->where('status', 'active')->value('id');
        $seniorClasses  = DB::table('course_classes')
            ->where('school_id', $schoolId)
            ->whereIn('name', ['Class 9', 'Class 10'])
            ->pluck('id');

        $seniorStudentIds = DB::table('student_academic_histories')
            ->whereIn('class_id', $seniorClasses)
            ->where('academic_year_id', $academicYearId)
            ->pluck('student_id')
            ->take(20)
            ->toArray();

        $allOccupiedBedIds = collect($bedIds)
            ->flatten()
            ->filter(function ($bedId) {
                return DB::table('hostel_beds')->where('id', $bedId)->value('status') === 'Occupied';
            })
            ->values()
            ->toArray();

        $relations  = ['Father', 'Mother', 'Uncle', 'Aunt', 'Elder Brother'];
        $mesTypes   = ['Veg', 'Non-Veg', 'Veg', 'Veg', 'Non-Veg'];

        foreach (array_slice($seniorStudentIds, 0, min(count($allOccupiedBedIds), count($seniorStudentIds))) as $idx => $studentId) {
            DB::table('hostel_students')->insertGetId([
                'school_id'        => $schoolId,
                'student_id'       => $studentId,
                'hostel_bed_id'    => $allOccupiedBedIds[$idx],
                'admission_date'   => '2026-04-01',
                'guardian_name'    => 'Guardian ' . ($idx + 1),
                'guardian_phone'   => '98110' . str_pad($idx + 10001, 5, '0', STR_PAD_LEFT),
                'guardian_relation'=> $relations[$idx % count($relations)],
                'medical_info'     => $idx % 5 === 0 ? 'Mild asthma — has inhaler' : null,
                'mess_type'        => $mesTypes[$idx % count($mesTypes)],
                'status'           => 'Active',
                'created_at'       => $now, 'updated_at' => $now,
            ]);
        }

        // ── 5. Mess Menus ──────────────────────────────────────────────────────
        $days      = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $mealTypes = ['Breakfast', 'Lunch', 'Evening Snack', 'Dinner'];
        $menus = [
            'Breakfast'     => ['Poha + Tea', 'Idli Sambar + Coffee', 'Paratha + Curd', 'Upma + Tea', 'Bread Butter + Milk', 'Aloo Puri + Tea', 'Dosa + Sambar'],
            'Lunch'         => ['Dal Rice + Sabzi + Roti', 'Rajma Rice + Salad', 'Chole Chawal + Roti', 'Mix Veg + Rice + Roti', 'Dal Fry + Jeera Rice', 'Kadhi Chawal + Roti', 'Paneer Masala + Rice'],
            'Evening Snack' => ['Samosa + Tea', 'Bread Pakora + Juice', 'Bhel Puri + Nimbu Pani', 'Vada Pav + Tea', 'Biscuits + Milk', 'Popcorn + Cold Drink', 'Maggi + Tea'],
            'Dinner'        => ['Roti + Dal + Sabzi', 'Noodles + Soup', 'Fried Rice + Manchurian', 'Roti + Paneer + Salad', 'Rice + Dal Tadka + Papad', 'Roti + Mixed Dal + Pickle', 'Khichdi + Kadhi'],
        ];

        foreach ([$boysHostelId, $girlsHostelId] as $hostelId) {
            foreach ($days as $dayIdx => $day) {
                foreach ($mealTypes as $meal) {
                    DB::table('hostel_mess_menus')->insert([
                        'school_id'  => $schoolId,
                        'hostel_id'  => $hostelId,
                        'day'        => $day,
                        'meal_type'  => $meal,
                        'items'      => $menus[$meal][$dayIdx],
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        // ── 6. Hostel Leave Requests ───────────────────────────────────────────
        $hostelStudents = DB::table('hostel_students')->where('school_id', $schoolId)->pluck('student_id')->toArray();
        $leaveTypes     = ['Day Out', 'Night Out', 'Home Time', 'Emergency'];
        $statuses       = ['Approved', 'Approved', 'Pending', 'Rejected', 'Out', 'Returned'];
        $reasons        = [
            'Going home for festival',
            'Medical appointment with family doctor',
            'Parents visiting — day outing',
            'Family function / wedding',
            'Emergency — grandparent hospitalised',
            'School sports event in another city',
        ];

        foreach (array_slice($hostelStudents, 0, min(15, count($hostelStudents))) as $idx => $studentId) {
            $from   = Carbon::now()->subDays(rand(5, 60));
            $to     = $from->copy()->addHours(rand(8, 48));
            $status = $statuses[$idx % count($statuses)];
            DB::table('hostel_leave_requests')->insert([
                'school_id'      => $schoolId,
                'student_id'     => $studentId,
                'leave_type'     => $leaveTypes[$idx % count($leaveTypes)],
                'from_date'      => $from,
                'to_date'        => $to,
                'reason'         => $reasons[$idx % count($reasons)],
                'status'         => $status,
                'approved_by'    => in_array($status, ['Approved', 'Rejected']) ? $adminUserId : null,
                'actual_out_time'=> in_array($status, ['Out', 'Returned']) ? $from->addMinutes(30) : null,
                'actual_in_time' => $status === 'Returned' ? $to->addHours(1) : null,
                'created_at'     => $from->subDays(1), 'updated_at' => $from,
            ]);
        }

        // ── 7. Hostel Visitors ─────────────────────────────────────────────────
        foreach (array_slice($hostelStudents, 0, min(10, count($hostelStudents))) as $idx => $studentId) {
            $date = Carbon::now()->subDays(rand(1, 30));
            DB::table('hostel_visitors')->insert([
                'school_id'    => $schoolId,
                'student_id'   => $studentId,
                'visitor_name' => 'Parent/Guardian ' . ($idx + 1),
                'relation'     => $relations[$idx % count($relations)],
                'phone'        => '98110' . str_pad($idx + 20001, 5, '0', STR_PAD_LEFT),
                'date'         => $date->format('Y-m-d'),
                'in_time'      => '10:30:00',
                'out_time'     => '13:00:00',
                'purpose'      => 'Family visit',
                'is_approved'  => true,
                'visitor_type' => 'Parent',
                'meet_user_type' => 'Student',
                'visitor_count'  => 1,
                'created_at'   => $date, 'updated_at' => $date,
            ]);
        }

        // ── 8. Hostel Complaints ───────────────────────────────────────────────
        $categories = ['maintenance', 'electrical', 'plumbing', 'cleanliness', 'furniture'];
        $titles     = [
            'maintenance'  => 'Door hinge broken in room',
            'electrical'   => 'Tube light not working',
            'plumbing'     => 'Water tap leaking',
            'cleanliness'  => 'Washroom not cleaned for 2 days',
            'furniture'    => 'Study table drawer stuck',
        ];

        foreach (array_slice($hostelStudents, 0, min(8, count($hostelStudents))) as $idx => $studentId) {
            $cat    = $categories[$idx % count($categories)];
            $userRow = DB::table('students')->find($studentId);
            $userId = $userRow ? DB::table('users')->where('email', "student_{$studentId}@school.com")->value('id') : $adminUserId;

            DB::table('hostel_complaints')->insert([
                'school_id'        => $schoolId,
                'hostel_id'        => ($idx % 2 === 0) ? $boysHostelId : $girlsHostelId,
                'student_id'       => $studentId,
                'reported_by'      => $adminUserId,
                'category'         => $cat,
                'title'            => $titles[$cat],
                'description'      => 'Reported by student. Needs immediate attention.',
                'location'         => 'Block A, Room 10' . ($idx + 1),
                'priority'         => ['low', 'medium', 'high'][$idx % 3],
                'status'           => ['open', 'in_progress', 'resolved'][$idx % 3],
                'assigned_to'      => $adminUserId,
                'resolution_notes' => $idx % 3 === 2 ? 'Issue fixed by maintenance team.' : null,
                'resolved_at'      => $idx % 3 === 2 ? $now : null,
                'created_at'       => Carbon::now()->subDays(rand(1, 15)),
                'updated_at'       => $now,
            ]);
        }

        $this->command->info('✅ Hostel Module seeded!');
        $this->command->info('   - 2 Hostels (Boys + Girls)');
        $this->command->info('   - ' . count($roomsData) . ' Rooms with beds');
        $this->command->info('   - ' . min(count($allOccupiedBedIds), count($seniorStudentIds)) . ' Hostel Students');
        $this->command->info('   - ' . (count($days) * count($mealTypes) * 2) . ' Mess Menu entries');
        $this->command->info('   - Leave requests, Visitors, Complaints seeded');
    }
}
