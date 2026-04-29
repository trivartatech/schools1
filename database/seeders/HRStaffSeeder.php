<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class HRStaffSeeder extends Seeder
{
    public function run(): void
    {
        $schoolId = 1; // DPS North Campus
        $now      = Carbon::now();

        // Disable FK checks
        Schema::disableForeignKeyConstraints();
        DB::table('payrolls')->truncate();
        DB::table('leaves')->truncate();
        DB::table('staff')->whereIn('school_id', [$schoolId])->delete();
        DB::table('designations')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // ── 1. HR Departments (add non-academic ones) ─────────────────────────
        $hrDepts = [
            ['name' => 'Administration',    'type' => 'administrative'],
            ['name' => 'Teaching - Primary', 'type' => 'teaching'],
            ['name' => 'Teaching - Secondary','type' => 'teaching'],
            ['name' => 'Library',            'type' => 'non_teaching'],
            ['name' => 'IT & Systems',       'type' => 'non_teaching'],
            ['name' => 'Accounts & Finance', 'type' => 'administrative'],
            ['name' => 'Sports & Activities','type' => 'non_teaching'],
            ['name' => 'Support Staff',      'type' => 'support'],
        ];
        $deptIds = [];
        foreach ($hrDepts as $d) {
            // Upsert: insert only if not already existing
            $exists = DB::table('departments')->where('school_id', $schoolId)->where('name', $d['name'])->first();
            if ($exists) {
                $deptIds[$d['name']] = $exists->id;
            } else {
                $deptIds[$d['name']] = DB::table('departments')->insertGetId(array_merge($d, [
                    'school_id' => $schoolId, 'created_at' => $now, 'updated_at' => $now,
                ]));
            }
        }

        // ── 2. Designations with Parent Hierarchy ─────────────────────────────
        // Level 1  — Top
        $principal  = DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => null, 'name' => 'Principal',         'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $vp         = DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => null, 'name' => 'Vice Principal',    'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        // Level 2 — Under VP
        $hod        = DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => $vp,'name' => 'Head of Department', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $coordinator= DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => $vp,'name' => 'Academic Coordinator','is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        // Level 3 — Teachers under HOD
        $pgTeacher  = DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => $hod,'name' => 'PGT Teacher',       'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $tgtTeacher = DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => $hod,'name' => 'TGT Teacher',       'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $prtTeacher = DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => $hod,'name' => 'PRT Teacher',       'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        // Support roles
        $accountant = DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => null,'name' => 'Accountant',        'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $librarian  = DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => null,'name' => 'Librarian',         'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $peon       = DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => null,'name' => 'Office Assistant',   'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $ptTeacher  = DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => null,'name' => 'PT Teacher',        'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $driver     = DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => null,'name' => 'Driver',            'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $conductor  = DB::table('designations')->insertGetId(['school_id' => $schoolId, 'parent_id' => null,'name' => 'Conductor',         'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        // ── 3. Staff Members ─────────────────────────────────────────────────
        $staffData = [
            // Principals & Management             user_type / spatie_role
            ['name' => 'Dr. Meera Sharma',     'email' => 'principal@dps.com',   'phone' => '9811001001', 'emp' => 'DPS-001', 'dept' => 'Administration',     'desig' => $principal,  'basic' => 95000, 'exp' => 22, 'qual' => 'Ph.D. Education',      'join' => '2003-07-01', 'bank' => 'SBI',   'ifsc' => 'SBIN0001111', 'pan' => 'ABCPM1234D', 'user_type' => 'principal',  'role' => 'principal'],
            ['name' => 'Mr. Rajesh Kumar',     'email' => 'vp@dps.com',          'phone' => '9811001002', 'emp' => 'DPS-002', 'dept' => 'Administration',     'desig' => $vp,         'basic' => 80000, 'exp' => 18, 'qual' => 'M.Ed.',                 'join' => '2006-06-15', 'bank' => 'HDFC',  'ifsc' => 'HDFC0001234', 'pan' => 'BCDRK5678E', 'user_type' => 'principal',  'role' => 'principal'],
            // HOD
            ['name' => 'Mrs. Sunita Gupta',    'email' => 'hod.science@dps.com', 'phone' => '9811002001', 'emp' => 'DPS-003', 'dept' => 'Teaching - Secondary','desig' => $hod,        'basic' => 62000, 'exp' => 14, 'qual' => 'M.Sc., B.Ed.',         'join' => '2010-04-01', 'bank' => 'PNB',   'ifsc' => 'PUNB0042100', 'pan' => 'CDEPG9012F', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mr. Arvind Singh',     'email' => 'hod.math@dps.com',    'phone' => '9811002002', 'emp' => 'DPS-004', 'dept' => 'Teaching - Secondary','desig' => $hod,        'basic' => 60000, 'exp' => 12, 'qual' => 'M.Sc. Math, B.Ed.',    'join' => '2012-07-01', 'bank' => 'HDFC',  'ifsc' => 'HDFC0002345', 'pan' => 'DEFAS3456G', 'user_type' => 'teacher',    'role' => 'teacher'],
            // Coord
            ['name' => 'Mrs. Kavita Joshi',    'email' => 'coord@dps.com',       'phone' => '9811003001', 'emp' => 'DPS-005', 'dept' => 'Teaching - Primary',  'desig' => $coordinator,'basic' => 52000, 'exp' => 10, 'qual' => 'B.Ed.',                 'join' => '2014-04-01', 'bank' => 'ICICI', 'ifsc' => 'ICIC0001111', 'pan' => 'EFGKJ7890H', 'user_type' => 'teacher',    'role' => 'teacher'],
            // PGT
            ['name' => 'Mr. Sandeep Mehta',    'email' => 'pgt.phy@dps.com',     'phone' => '9811004001', 'emp' => 'DPS-006', 'dept' => 'Teaching - Secondary','desig' => $pgTeacher,  'basic' => 52000, 'exp' => 8,  'qual' => 'M.Sc. Physics, B.Ed.','join' => '2016-06-01', 'bank' => 'SBI',   'ifsc' => 'SBIN0002222', 'pan' => 'FGHSM2345I', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mrs. Priya Verma',     'email' => 'pgt.che@dps.com',     'phone' => '9811004002', 'emp' => 'DPS-007', 'dept' => 'Teaching - Secondary','desig' => $pgTeacher,  'basic' => 50000, 'exp' => 7,  'qual' => 'M.Sc. Chemistry',      'join' => '2017-07-01', 'bank' => 'HDFC',  'ifsc' => 'HDFC0003456', 'pan' => 'GHIPV6789J', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Dr. Anita Kapoor',     'email' => 'pgt.bio@dps.com',     'phone' => '9811004003', 'emp' => 'DPS-008', 'dept' => 'Teaching - Secondary','desig' => $pgTeacher,  'basic' => 51000, 'exp' => 9,  'qual' => 'M.Sc. Biology, B.Ed.','join' => '2015-04-01', 'bank' => 'PNB',   'ifsc' => 'PUNB0053200', 'pan' => 'HIJAK0123K', 'user_type' => 'teacher',    'role' => 'teacher'],
            // TGT
            ['name' => 'Mr. Vikram Patel',     'email' => 'tgt.eng@dps.com',     'phone' => '9811005001', 'emp' => 'DPS-009', 'dept' => 'Teaching - Secondary','desig' => $tgtTeacher, 'basic' => 44000, 'exp' => 6,  'qual' => 'M.A. English, B.Ed.', 'join' => '2018-07-01', 'bank' => 'ICICI', 'ifsc' => 'ICIC0002222', 'pan' => 'IJKVP4567L', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mrs. Ritu Sharma',     'email' => 'tgt.sst@dps.com',     'phone' => '9811005002', 'emp' => 'DPS-010', 'dept' => 'Teaching - Secondary','desig' => $tgtTeacher, 'basic' => 43000, 'exp' => 5,  'qual' => 'M.A. History, B.Ed.', 'join' => '2019-04-01', 'bank' => 'SBI',   'ifsc' => 'SBIN0003333', 'pan' => 'JKLRS8901M', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mr. Anil Tiwari',      'email' => 'tgt.hin@dps.com',     'phone' => '9811005003', 'emp' => 'DPS-011', 'dept' => 'Teaching - Secondary','desig' => $tgtTeacher, 'basic' => 42000, 'exp' => 4,  'qual' => 'M.A. Hindi, B.Ed.',   'join' => '2020-07-01', 'bank' => 'HDFC',  'ifsc' => 'HDFC0004567', 'pan' => 'KLMAT2345N', 'user_type' => 'teacher',    'role' => 'teacher'],
            // PRT
            ['name' => 'Mrs. Deepa Thomas',    'email' => 'prt1@dps.com',        'phone' => '9811006001', 'emp' => 'DPS-012', 'dept' => 'Teaching - Primary',  'desig' => $prtTeacher, 'basic' => 36000, 'exp' => 5,  'qual' => 'B.Ed.',                'join' => '2019-06-01', 'bank' => 'SBI',   'ifsc' => 'SBIN0004444', 'pan' => 'LMNDT6789O', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mrs. Shalini Roy',     'email' => 'prt2@dps.com',        'phone' => '9811006002', 'emp' => 'DPS-013', 'dept' => 'Teaching - Primary',  'desig' => $prtTeacher, 'basic' => 35000, 'exp' => 4,  'qual' => 'B.El.Ed.',             'join' => '2020-04-01', 'bank' => 'PNB',   'ifsc' => 'PUNB0064300', 'pan' => 'MNOSR0123P', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mr. Rohit Chauhan',    'email' => 'prt3@dps.com',        'phone' => '9811006003', 'emp' => 'DPS-014', 'dept' => 'Teaching - Primary',  'desig' => $prtTeacher, 'basic' => 35000, 'exp' => 3,  'qual' => 'B.Ed.',                'join' => '2021-07-01', 'bank' => 'ICICI', 'ifsc' => 'ICIC0003333', 'pan' => 'NOPRC4567Q', 'user_type' => 'teacher',    'role' => 'teacher'],
            // Non-Teaching
            ['name' => 'Mr. Suresh Agarwal',   'email' => 'accounts@dps.com',    'phone' => '9811007001', 'emp' => 'DPS-015', 'dept' => 'Accounts & Finance',  'desig' => $accountant, 'basic' => 40000, 'exp' => 10, 'qual' => 'B.Com, CA Inter',      'join' => '2014-08-01', 'bank' => 'SBI',   'ifsc' => 'SBIN0005555', 'pan' => 'OPQSA8901R', 'user_type' => 'accountant', 'role' => 'accountant'],
            ['name' => 'Mrs. Lakshmi Nair',    'email' => 'library@dps.com',     'phone' => '9811007002', 'emp' => 'DPS-016', 'dept' => 'Library',             'desig' => $librarian,  'basic' => 30000, 'exp' => 8,  'qual' => 'B.Lib.Sc.',            'join' => '2016-04-01', 'bank' => 'HDFC',  'ifsc' => 'HDFC0005678', 'pan' => 'PQRLN2345S', 'user_type' => 'teacher',    'role' => 'librarian'],
            ['name' => 'Mr. Manish Yadav',     'email' => 'pt@dps.com',          'phone' => '9811007003', 'emp' => 'DPS-017', 'dept' => 'Sports & Activities', 'desig' => $ptTeacher,  'basic' => 32000, 'exp' => 6,  'qual' => 'B.P.Ed.',              'join' => '2018-04-01', 'bank' => 'PNB',   'ifsc' => 'PUNB0075400', 'pan' => 'QRSMY6789T', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mr. Kishore Das',      'email' => 'it@dps.com',          'phone' => '9811007004', 'emp' => 'DPS-018', 'dept' => 'IT & Systems',        'desig' => $peon,       'basic' => 35000, 'exp' => 5,  'qual' => 'B.Tech (CS)',          'join' => '2019-08-01', 'bank' => 'ICICI', 'ifsc' => 'ICIC0004444', 'pan' => 'RSTKD0123U', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mrs. Geeta Batra',     'email' => 'office@dps.com',      'phone' => '9811007005', 'emp' => 'DPS-019', 'dept' => 'Administration',      'desig' => $peon,       'basic' => 25000, 'exp' => 3,  'qual' => '12th Pass',            'join' => '2021-04-01', 'bank' => 'SBI',   'ifsc' => 'SBIN0006666', 'pan' => 'STUGB4567V', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mr. Ramu Prasad',      'email' => 'support@dps.com',     'phone' => '9811007006', 'emp' => 'DPS-020', 'dept' => 'Support Staff',       'desig' => $peon,       'basic' => 18000, 'exp' => 2,  'qual' => '10th Pass',            'join' => '2022-06-01', 'bank' => 'PNB',   'ifsc' => 'PUNB0086500', 'pan' => 'TUVRP8901W', 'user_type' => 'teacher',    'role' => 'teacher'],
            // Drivers (one per route bus) — Karnataka commercial driving licenses
            ['name' => 'Mr. Hanumantha Naik',  'email' => 'driver1@dps.com',     'phone' => '9811008001', 'emp' => 'DPS-021', 'dept' => 'Support Staff',       'desig' => $driver,     'basic' => 22000, 'exp' => 12, 'qual' => 'Heavy MV License (KA)',    'join' => '2014-06-01', 'bank' => 'Canara','ifsc' => 'CNRB0001234', 'pan' => 'UVWHN1234X', 'user_type' => 'driver',     'role' => 'driver'],
            ['name' => 'Mr. Veeresh Patil',    'email' => 'driver2@dps.com',     'phone' => '9811008002', 'emp' => 'DPS-022', 'dept' => 'Support Staff',       'desig' => $driver,     'basic' => 21000, 'exp' => 10, 'qual' => 'Heavy MV License (KA)',    'join' => '2016-04-01', 'bank' => 'Karnataka Bank','ifsc' => 'KARB0000123', 'pan' => 'VWXVP5678Y', 'user_type' => 'driver',     'role' => 'driver'],
            ['name' => 'Mr. Mahesh Hadapad',   'email' => 'driver3@dps.com',     'phone' => '9811008003', 'emp' => 'DPS-023', 'dept' => 'Support Staff',       'desig' => $driver,     'basic' => 21000, 'exp' => 9,  'qual' => 'Heavy MV License (KA)',    'join' => '2017-07-01', 'bank' => 'SBI',   'ifsc' => 'SBIN0007777', 'pan' => 'WXYMH9012Z', 'user_type' => 'driver',     'role' => 'driver'],
            ['name' => 'Mr. Lokesh Kumar',     'email' => 'driver4@dps.com',     'phone' => '9811008004', 'emp' => 'DPS-024', 'dept' => 'Support Staff',       'desig' => $driver,     'basic' => 20000, 'exp' => 7,  'qual' => 'Heavy MV License (KA)',    'join' => '2019-04-01', 'bank' => 'PNB',   'ifsc' => 'PUNB0097600', 'pan' => 'XYZLK3456A', 'user_type' => 'driver',     'role' => 'driver'],
            ['name' => 'Mr. Suresh Bandi',     'email' => 'driver5@dps.com',     'phone' => '9811008005', 'emp' => 'DPS-025', 'dept' => 'Support Staff',       'desig' => $driver,     'basic' => 20000, 'exp' => 6,  'qual' => 'Heavy MV License (KA)',    'join' => '2020-06-01', 'bank' => 'HDFC',  'ifsc' => 'HDFC0006789', 'pan' => 'YZASB7890B', 'user_type' => 'driver',     'role' => 'driver'],
            // Conductors (one per route bus)
            ['name' => 'Mr. Manjunath G',      'email' => 'conductor1@dps.com',  'phone' => '9811009001', 'emp' => 'DPS-026', 'dept' => 'Support Staff',       'desig' => $conductor,  'basic' => 16000, 'exp' => 8,  'qual' => 'PUC, First Aid',           'join' => '2016-07-01', 'bank' => 'Canara','ifsc' => 'CNRB0002345', 'pan' => 'ZABMG1234C', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mr. Venkatesh M',      'email' => 'conductor2@dps.com',  'phone' => '9811009002', 'emp' => 'DPS-027', 'dept' => 'Support Staff',       'desig' => $conductor,  'basic' => 16000, 'exp' => 6,  'qual' => 'SSLC, First Aid',          'join' => '2018-04-01', 'bank' => 'Karnataka Bank','ifsc' => 'KARB0000234', 'pan' => 'BCDVM5678D', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mr. Nagaraj K',        'email' => 'conductor3@dps.com',  'phone' => '9811009003', 'emp' => 'DPS-028', 'dept' => 'Support Staff',       'desig' => $conductor,  'basic' => 15000, 'exp' => 5,  'qual' => 'SSLC, First Aid',          'join' => '2019-06-01', 'bank' => 'SBI',   'ifsc' => 'SBIN0008888', 'pan' => 'CDENK9012E', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mr. Basavaraj N',      'email' => 'conductor4@dps.com',  'phone' => '9811009004', 'emp' => 'DPS-029', 'dept' => 'Support Staff',       'desig' => $conductor,  'basic' => 15000, 'exp' => 4,  'qual' => 'SSLC, First Aid',          'join' => '2020-07-01', 'bank' => 'PNB',   'ifsc' => 'PUNB0108700', 'pan' => 'DEFBN3456F', 'user_type' => 'teacher',    'role' => 'teacher'],
            ['name' => 'Mr. Shivakumar R',     'email' => 'conductor5@dps.com',  'phone' => '9811009005', 'emp' => 'DPS-030', 'dept' => 'Support Staff',       'desig' => $conductor,  'basic' => 15000, 'exp' => 3,  'qual' => 'PUC, First Aid',           'join' => '2021-04-01', 'bank' => 'HDFC',  'ifsc' => 'HDFC0007890', 'pan' => 'EFGSR7890G', 'user_type' => 'teacher',    'role' => 'teacher'],
        ];

        $orgId = DB::table('schools')->find($schoolId)->organization_id ?? 1;

        $staffIds = [];
        $userIdsMap = [];

        foreach ($staffData as $s) {
            // Check if user already exists (e.g., principal)
            $userModel = \App\Models\User::where('email', $s['email'])->first();
            if (!$userModel) {
                $userModel = \App\Models\User::create([
                    'school_id'       => $schoolId,
                    'organization_id' => $orgId,
                    'name'            => $s['name'],
                    'email'           => $s['email'],
                    'phone'           => $s['phone'],
                    'password'        => Hash::make('password'),
                    'user_type'       => $s['user_type'],
                    'is_active'       => true,
                ]);
            } else {
                // Update user_type in case it was wrong
                $userModel->update(['user_type' => $s['user_type']]);
            }
            $userId = $userModel->id;

            // Assign Spatie role scoped to the school (teams feature requires team_id = school_id)
            $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($schoolId);
            $registrar->forgetCachedPermissions();
            $userModel->unsetRelation('permissions')->unsetRelation('roles');
            $userModel->syncRoles([$s['role']]);

            $staffId = DB::table('staff')->insertGetId([
                'school_id'      => $schoolId,
                'user_id'        => $userId,
                'department_id'  => $deptIds[$s['dept']],
                'designation_id' => $s['desig'],
                'employee_id'    => $s['emp'],
                'qualification'  => $s['qual'],
                'experience_years'=> $s['exp'],
                'joining_date'   => $s['join'],
                'basic_salary'   => $s['basic'],
                'bank_name'      => $s['bank'],
                'bank_account_no'=> '00' . rand(100000000, 999999999),
                'ifsc_code'      => $s['ifsc'],
                'pan_no'         => $s['pan'],
                'status'         => 'active',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            $staffIds[]       = $staffId;
            $userIdsMap[$staffId] = $userId;
        }

        // ── 4. Leave Records ───────────────────────────────────────────────────
        $leaveTypeMap = DB::table('leave_types')->where('school_id', $schoolId)->pluck('id', 'code')->toArray();
        // code → leave_type string mapping for the string column
        $leaveTypes = ['casual', 'sick', 'earned', 'maternity', 'unpaid', 'other'];
        $leaveCodeMap = ['casual' => 'CL', 'sick' => 'SL', 'earned' => 'EL', 'maternity' => 'ML', 'unpaid' => 'LWP', 'other' => 'SPL'];
        $leaveStatuses = [
            ['status' => 'approved'],
            ['status' => 'approved'],
            ['status' => 'pending'],
            ['status' => 'rejected'],
        ];
        $reasons = [
            'Fever and cold — doctor advised rest',
            'Family function in hometown',
            'Child\'s school admission related work',
            'Personal medical checkup',
            'Attending a wedding ceremony',
            'Property registration visit',
            'Seasonal flu',
            'Travel for government document work',
        ];

        // Generate 30 realistic leave records across staff
        $adminUserId = DB::table('users')->where('email', 'principal@dps.com')->value('id') ?? 1;

        for ($i = 0; $i < 30; $i++) {
            $staffId  = $staffIds[array_rand($staffIds)];
            $userId   = $userIdsMap[$staffId];
            $startDay = Carbon::now()->subDays(rand(5, 180));
            $duration = rand(1, 5);
            $endDay   = $startDay->copy()->addDays($duration - 1);
            $statusInfo = $leaveStatuses[array_rand($leaveStatuses)];
            $type = $leaveTypes[array_rand($leaveTypes)];

            $code = $leaveCodeMap[$type] ?? 'CL';
            DB::table('leaves')->insert([
                'school_id'     => $schoolId,
                'user_id'       => $userId,
                'leave_type'    => $type,
                'leave_type_id' => $leaveTypeMap[$code] ?? null,
                'start_date'    => $startDay->format('Y-m-d'),
                'end_date'      => $endDay->format('Y-m-d'),
                'reason'        => $reasons[array_rand($reasons)],
                'status'        => $statusInfo['status'],
                'approved_by'   => $statusInfo['status'] !== 'pending' ? $adminUserId : null,
                'created_at'    => $startDay->subDays(2),
                'updated_at'    => $startDay->subDays(1),
            ]);
        }

        // Fetch unpaid leave counts per user per month (driver-aware: MySQL vs SQLite)
        $isSqlite  = DB::connection()->getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? 'CAST(strftime("%m", start_date) AS INTEGER)' : 'MONTH(start_date)';
        $yearExpr  = $isSqlite ? 'CAST(strftime("%Y", start_date) AS INTEGER)' : 'YEAR(start_date)';
        $daysExpr  = $isSqlite
            ? 'SUM(CAST(julianday(end_date) - julianday(start_date) + 1 AS INTEGER))'
            : 'SUM(DATEDIFF(end_date, start_date) + 1)';

        $unpaidLeaves = DB::table('leaves')
            ->select(DB::raw("user_id, $monthExpr as m, $yearExpr as y, $daysExpr as days"))
            ->where('leave_type', 'unpaid')
            ->where('status', 'approved')
            ->groupBy('user_id', DB::raw($monthExpr), DB::raw($yearExpr))
            ->get()
            ->keyBy(function($item) {
                return $item->user_id . '-' . $item->m . '-' . $item->y;
            });

        // ── 5. Payroll Records (last 3 months) ────────────────────────────────
        $months = [
            ['month' => 12, 'year' => 2025, 'payment_date' => '2025-01-01'],
            ['month' => 1,  'year' => 2026, 'payment_date' => '2026-02-01'],
            ['month' => 2,  'year' => 2026, 'payment_date' => '2026-03-03'],
        ];

        foreach ($staffIds as $staffId) {
            $basic = DB::table('staff')->where('id', $staffId)->value('basic_salary') ?: 30000;
            $da    = round($basic * 0.52, 2); // 52% DA (govt pattern)
            $hra   = round($basic * 0.24, 2); // 24% HRA
            $ta    = 1600;                     // Fixed TA
            $gross = $basic + $da + $hra + $ta;

            $pf    = round($basic * 0.12, 2); // 12% PF deduction
            $esi   = $gross <= 21000 ? round($gross * 0.0075, 2) : 0; // ESI only if gross <= 21k
            $tds   = $gross > 50000 ? round($gross * 0.02, 2) : 0;
            $totalDeductions = $pf + $esi + $tds;
            $net   = round($gross - $totalDeductions, 2);

            foreach ($months as $m) {
                $userId = $userIdsMap[$staffId];
                $key = $userId . '-' . $m['month'] . '-' . $m['year'];
                $unpaidDays = isset($unpaidLeaves[$key]) ? $unpaidLeaves[$key]->days : 0;
                
                $unpaidDeduction = 0;
                if ($unpaidDays > 0) {
                    $unpaidDeduction = round(($gross / 30) * $unpaidDays, 2);
                }

                $finalDeductions = $totalDeductions + $unpaidDeduction;
                $netPay = round($gross - $finalDeductions, 2);

                DB::table('payrolls')->insert([
                    'school_id'    => $schoolId,
                    'staff_id'     => $staffId,
                    'month'        => $m['month'],
                    'year'         => $m['year'],
                    'basic_pay'    => $basic,
                    'allowances'   => $da + $hra + $ta,
                    'deductions'   => $finalDeductions,
                    'unpaid_leave_days' => $unpaidDays,
                    'unpaid_leave_deduction' => $unpaidDeduction,
                    'net_salary'   => $netPay,
                    'status'       => 'paid',
                    'payment_date' => $m['payment_date'],
                    'payment_mode' => 'bank_transfer',
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }

        // ── 6. Create Random School Expenses ─────────────────────────────────
        $expenseCategories = [
            ['name' => 'Electricity & Utilities', 'description' => 'Power, water and internet bills'],
            ['name' => 'Maintenance & Repairs', 'description' => 'School building and grounds upkeep'],
            ['name' => 'IT & Software Subscriptions', 'description' => 'ERP, Zoom, etc.'],
            ['name' => 'Library Books', 'description' => 'New acquisitions'],
            ['name' => 'Lab Equipment', 'description' => 'Chemicals and science supplies'],
            ['name' => 'Event Management', 'description' => 'Annual day, sports day'],
            ['name' => 'Transport Fuel', 'description' => 'Diesel for buses']
        ];
        
        $categoryIdMap = [];
        foreach($expenseCategories as $cat) {
            $catId = DB::table('expense_categories')->insertGetId(array_merge($cat, ['school_id' => $schoolId, 'created_at' => $now, 'updated_at' => $now]));
            $categoryIdMap[$cat['name']] = $catId;
        }

        $expenseDesc = [
            'Electricity & Utilities' => 'BESCOM monthly bill',
            'Maintenance & Repairs' => 'Plumbing repair in Block C',
            'IT & Software Subscriptions' => 'AWS Cloud Hosting',
            'Library Books' => 'Purchase of 50 new reference books',
            'Lab Equipment' => 'Chemicals for Chemistry Lab',
            'Event Management' => 'Tent and seating for Annual Day',
            'Transport Fuel' => 'Diesel for Bus Routes 1-5'
        ];

        // Seed 20 random expenses over the last 6 months
        $activeYearId = DB::table('academic_years')->where('school_id', $schoolId)->where('status', 'active')->value('id');

        for ($i = 0; $i < 20; $i++) {
            $catName = array_rand($categoryIdMap);
            $expDate = Carbon::now()->subDays(rand(1, 180));
            DB::table('expenses')->insert([
                'school_id' => $schoolId,
                'academic_year_id' => $activeYearId,
                'expense_category_id' => $categoryIdMap[$catName],
                'amount' => rand(5000, 50000),
                'expense_date' => $expDate->format('Y-m-d'),
                'title' => $expenseDesc[$catName],
                'payment_mode' => 'online',
                'transaction_ref' => 'REF-' . rand(10000, 99999),
                'created_at' => $expDate,
                'updated_at' => $expDate,
            ]);
        }

        // ── 7. Assign incharge_staff_id to classes & sections ─────────────────
        $teacherStaffIds = DB::table('staff')
            ->where('school_id', $schoolId)
            ->whereIn('designation_id', [$pgTeacher, $tgtTeacher, $prtTeacher, $coordinator, $hod])
            ->pluck('id')
            ->shuffle()
            ->values()
            ->toArray();

        $classes  = DB::table('course_classes')->where('school_id', $schoolId)->get();
        $tIdx = 0;
        foreach ($classes as $cls) {
            $assignedId = $teacherStaffIds[$tIdx % count($teacherStaffIds)];
            DB::table('course_classes')->where('id', $cls->id)->update(['incharge_staff_id' => $assignedId]);

            $sections = DB::table('sections')->where('course_class_id', $cls->id)->get();
            foreach ($sections as $sec) {
                $secAssigned = $teacherStaffIds[$tIdx % count($teacherStaffIds)];
                DB::table('sections')->where('id', $sec->id)->update(['incharge_staff_id' => $secAssigned]);
                $tIdx++;
            }
        }

        // Assign incharge to class_subjects too
        DB::table('class_subjects')->where('school_id', $schoolId)->get()->each(function ($cs) use ($teacherStaffIds, &$tIdx) {
            DB::table('class_subjects')->where('id', $cs->id)->update([
                'incharge_staff_id' => $teacherStaffIds[$tIdx++ % count($teacherStaffIds)],
            ]);
        });

        $this->command->info('✅ HR Staff Module seeded successfully!');
        $this->command->info('   - ' . count($hrDepts) . ' HR Departments');
        $this->command->info('   - 13 Designations (incl. Driver, Conductor)');
        $this->command->info('   - ' . count($staffData) . ' Staff Members with User accounts');
        $this->command->info('   - 30 Leave Records (approved/pending/rejected)');
        $this->command->info('   - ' . (count($staffIds) * count($months)) . ' Payroll entries (3 months)');
    }
}
