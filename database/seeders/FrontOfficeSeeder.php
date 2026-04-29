<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FrontOfficeSeeder extends Seeder
{
    public function run(): void
    {
        $school         = DB::table('schools')->first();
        $schoolId       = $school->id;
        $now            = Carbon::now();
        $academicYearId = DB::table('academic_years')->where('school_id', $schoolId)->where('status', 'active')->value('id');
        $adminUserId    = DB::table('users')->where('school_id', $schoolId)->whereIn('user_type', ['principal', 'admin'])->value('id');
        $deptIds        = DB::table('departments')->where('school_id', $schoolId)->pluck('id', 'name')->toArray();

        // Clear existing data
        Schema::disableForeignKeyConstraints();
        DB::table('visitor_logs')->where('school_id', $schoolId)->delete();
        DB::table('gate_passes')->where('school_id', $schoolId)->delete();
        DB::table('complaints')->where('school_id', $schoolId)->delete();
        DB::table('correspondences')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // ── 1. Visitor Logs ────────────────────────────────────────────────────
        $visitorNames = [
            'Ramesh Sharma',   'Priya Verma',  'Mohan Lal',   'Sunita Devi',
            'Anil Kapoor',     'Geeta Bose',   'Vijay Kumar',  'Meena Trivedi',
            'Ravi Prakash',    'Savita Gupta', 'Deepak Nair',  'Rekha Singh',
            'Harish Pandey',   'Anjali Yadav', 'Suresh Mehta', 'Pooja Tiwari',
            'Naresh Joshi',    'Kavita Reddy', 'Vinod Mishra', 'Lalita Sharma',
        ];

        $purposes = ['Meeting', 'Admission', 'Delivery', 'Other'];
        $idTypes  = ['Aadhaar Card', 'PAN Card', 'Voter ID', 'Passport', 'Driving Licence'];

        // Get a staff user for person_to_meet
        $staffUserId = DB::table('users')->where('school_id', $schoolId)->where('user_type', 'teacher')->value('id') ?? $adminUserId;

        for ($i = 0; $i < count($visitorNames); $i++) {
            $inTime  = Carbon::now()->subDays(rand(0, 30))->setHour(rand(9, 14))->setMinute(rand(0, 59));
            $outTime = $inTime->copy()->addHours(rand(1, 3));
            $isPreReg = $i % 4 === 0;

            DB::table('visitor_logs')->insert([
                'school_id'         => $schoolId,
                'academic_year_id'  => $academicYearId,
                'name'              => $visitorNames[$i],
                'phone'             => '98' . rand(10000000, 99999999),
                'purpose'           => $purposes[$i % count($purposes)],
                'person_to_meet_type' => 'App\\Models\\User',
                'person_to_meet_id'   => $staffUserId,
                'in_time'           => $inTime,
                'out_time'          => $outTime,
                'notes'             => 'Visitor checked in at front gate.',
                'is_pre_registered' => $isPreReg,
                'expected_date'     => $isPreReg ? $inTime->format('Y-m-d') : null,
                'expected_time'     => $isPreReg ? $inTime->format('H:i') : null,
                'pre_registered_by' => $isPreReg ? $adminUserId : null,
                'badge_number'      => 'VB-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'id_type'           => $idTypes[$i % count($idTypes)],
                'id_number'         => strtoupper(Str::random(10)),
                'created_at'        => $inTime, 'updated_at' => $inTime,
            ]);
        }

        // ── 2. Gate Passes ─────────────────────────────────────────────────────
        $students = DB::table('student_academic_histories')
            ->where('academic_year_id', $academicYearId)
            ->pluck('student_id')
            ->take(15)
            ->toArray();

        $passStatuses = ['Approved', 'Approved', 'Pending', 'Rejected', 'Exited', 'Returned'];
        $reasons      = [
            'Doctor appointment — accompanied by parent',
            'Family function — early departure requested',
            'Sports event at district level',
            'Parent request for early pickup',
            'Medical emergency',
        ];

        foreach ($students as $idx => $studentId) {
            $exitTime   = Carbon::now()->subDays(rand(0, 20))->setHour(12)->setMinute(30);
            $status     = $passStatuses[$idx % count($passStatuses)];

            DB::table('gate_passes')->insert([
                'school_id'         => $schoolId,
                'academic_year_id'  => $academicYearId,
                'pass_type'         => 'Student',
                'user_type'         => 'App\\Models\\Student',
                'user_id'           => $studentId,
                'requested_by_type' => 'App\\Models\\User',
                'requested_by_id'   => $adminUserId,
                'verified_by'       => in_array($status, ['Approved', 'Exited', 'Returned']) ? $adminUserId : null,
                'verification_method' => 'Manual',
                'picked_up_by_name' => 'Parent/Guardian ' . ($idx + 1),
                'relationship'      => ['Father', 'Mother', 'Uncle'][($idx) % 3],
                'status'            => $status,
                'qr_code_token'     => Str::uuid()->toString(),
                'exit_time'         => in_array($status, ['Exited', 'Returned']) ? $exitTime : null,
                'return_time'       => $status === 'Returned' ? $exitTime->addHours(3) : null,
                'reason'            => $reasons[$idx % count($reasons)],
                'approval_notes'    => in_array($status, ['Approved', 'Exited', 'Returned']) ? 'Approved by principal.' : null,
                'created_at'        => $exitTime->subDays(1), 'updated_at' => $exitTime,
            ]);
        }

        // ── 3. Complaints ──────────────────────────────────────────────────────
        $complaintData = [
            ['type' => 'Facility',  'desc' => 'Water cooler on second floor not working for 3 days.', 'priority' => 'High'],
            ['type' => 'Transport', 'desc' => 'Bus Route B is consistently 20 minutes late.',          'priority' => 'Medium'],
            ['type' => 'Academic',  'desc' => 'Homework load for Class 8 is excessive.',               'priority' => 'Medium'],
            ['type' => 'Hostel',    'desc' => 'Hot water not available in Girls Hostel morning time.', 'priority' => 'High'],
            ['type' => 'Facility',  'desc' => 'Library AC not working.',                               'priority' => 'Low'],
            ['type' => 'Other',     'desc' => 'Canteen food quality has degraded recently.',           'priority' => 'Medium'],
            ['type' => 'Transport', 'desc' => 'Bus driver was rude to students.',                      'priority' => 'Critical'],
            ['type' => 'Academic',  'desc' => 'Lab not available for practical exams.',                'priority' => 'High'],
        ];

        $complaintStatuses = ['Open', 'In Progress', 'Resolved', 'Closed'];
        $deptId = !empty($deptIds) ? array_values($deptIds)[0] : null;

        foreach ($complaintData as $idx => $c) {
            $status     = $complaintStatuses[$idx % count($complaintStatuses)];
            $createdAt  = Carbon::now()->subDays(rand(1, 30));

            DB::table('complaints')->insert([
                'school_id'             => $schoolId,
                'academic_year_id'      => $academicYearId,
                'type'                  => $c['type'],
                'raised_by_type'        => 'App\\Models\\User',
                'raised_by_id'          => $adminUserId,
                'description'           => $c['desc'],
                'priority'              => $c['priority'],
                'assigned_department_id'=> $deptId,
                'assigned_to'           => $adminUserId,
                'status'                => $status,
                'resolution_notes'      => in_array($status, ['Resolved', 'Closed']) ? 'Issue addressed and resolved by concerned department.' : null,
                'resolved_at'           => in_array($status, ['Resolved', 'Closed']) ? $createdAt->addDays(3) : null,
                'sla_hours'             => 24,
                'escalation_level'      => 0,
                'sla_breached'          => false,
                'created_at'            => $createdAt, 'updated_at' => $createdAt,
            ]);
        }

        // ── 4. Correspondences ─────────────────────────────────────────────────
        $correspondencesData = [
            ['type' => 'Incoming', 'subject' => 'CBSE Circular — New Exam Pattern 2026-27',  'sender' => 'CBSE Regional Office',          'dept' => 'Administration',     'ref' => 'CBSE/RO/2026/041'],
            ['type' => 'Outgoing', 'subject' => 'Annual Report Submission to CBSE',           'sender' => 'DPS school1 Principal',         'dept' => 'Administration',     'ref' => 'DPS/OUT/2026/001'],
            ['type' => 'Incoming', 'subject' => 'MCD Water Supply Maintenance Notice',        'sender' => 'Delhi Municipal Corporation',   'dept' => 'Administration',     'ref' => 'MCD/WS/2026/112'],
            ['type' => 'Incoming', 'subject' => 'Parent Complaint — Fees Dispute',            'sender' => 'Mr. Ramesh Sharma (Parent)',     'dept' => 'Accounts & Finance', 'ref' => 'PAR/CMP/2026/001'],
            ['type' => 'Outgoing', 'subject' => 'Fee Reminder Notice to Defaulters',          'sender' => 'school1 Accounts Dept',         'dept' => 'Accounts & Finance', 'ref' => 'DPS/FEE/2026/012'],
            ['type' => 'Incoming', 'subject' => 'Fire Safety Audit Certificate Renewal',      'sender' => 'Delhi Fire Services',           'dept' => 'Administration',     'ref' => 'DFS/CERT/2026/089'],
            ['type' => 'Outgoing', 'subject' => 'Staff Recruitment Advertisement',            'sender' => 'DPS HR Department',             'dept' => 'Administration',     'ref' => 'DPS/HR/2026/007'],
            ['type' => 'Incoming', 'subject' => 'District Science Olympiad Invitation',       'sender' => 'District Education Office',     'dept' => 'Teaching - Secondary','ref' => 'DEO/OLY/2026/033'],
        ];

        $deliveryStatuses = ['delivered', 'delivered', 'pending', 'failed'];

        foreach ($correspondencesData as $idx => $c) {
            $date   = Carbon::now()->subDays(rand(1, 60));
            $deptId = $deptIds[$c['dept']] ?? (array_values($deptIds)[0] ?? null);
            $status = $deliveryStatuses[$idx % count($deliveryStatuses)];

            DB::table('correspondences')->insert([
                'school_id'           => $schoolId,
                'academic_year_id'    => $academicYearId,
                'type'                => $c['type'],
                'reference_number'    => $c['ref'],
                'sender_receiver_name'=> $c['sender'],
                'subject'             => $c['subject'],
                'department_id'       => $deptId,
                'date'                => $date->format('Y-m-d'),
                'courier_name'        => $c['type'] === 'Outgoing' ? 'Speed Post' : null,
                'dispatch_tracking'   => $c['type'] === 'Outgoing' ? 'SP' . rand(1000000, 9999999) . 'IN' : null,
                'notes'               => 'Received and filed in school records.',
                'acknowledged'        => $idx % 3 !== 2,
                'acknowledged_at'     => $idx % 3 !== 2 ? $date->addDay() : null,
                'acknowledged_by'     => $idx % 3 !== 2 ? 'School Principal' : null,
                'delivery_status'     => $status,
                'created_at'          => $date, 'updated_at' => $date,
            ]);
        }

        $this->command->info('✅ Front Office seeded!');
        $this->command->info('   - ' . count($visitorNames) . ' Visitor Logs');
        $this->command->info('   - ' . count($students) . ' Gate Passes');
        $this->command->info('   - ' . count($complaintData) . ' Complaints');
        $this->command->info('   - ' . count($correspondencesData) . ' Correspondences');
    }
}
