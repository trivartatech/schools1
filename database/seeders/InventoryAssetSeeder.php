<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class InventoryAssetSeeder extends Seeder
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
        DB::table('asset_maintenance')->where('school_id', $schoolId)->delete();
        DB::table('asset_assignments')->where('school_id', $schoolId)->delete();
        DB::table('assets')->where('school_id', $schoolId)->delete();
        DB::table('asset_categories')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // ── 1. Asset categories ───────────────────────────────────────────────
        $categories = [
            ['name' => 'Furniture',       'description' => 'Tables, chairs, benches, desks, cupboards'],
            ['name' => 'IT Equipment',    'description' => 'Computers, laptops, projectors, printers'],
            ['name' => 'Lab Equipment',   'description' => 'Microscopes, beakers, lab apparatus'],
            ['name' => 'Sports',          'description' => 'Sports gear, mats, equipment'],
            ['name' => 'Audio Visual',    'description' => 'Speakers, mics, AV systems'],
        ];
        $categoryIds = [];
        foreach ($categories as $cat) {
            $categoryIds[$cat['name']] = DB::table('asset_categories')->insertGetId([
                'school_id'   => $schoolId,
                'name'        => $cat['name'],
                'description' => $cat['description'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // ── 2. Assets (~30 records) ───────────────────────────────────────────
        $assets = [
            ['cat' => 'Furniture',     'name' => 'Student Bench (4-seater)',  'brand' => 'Godrej',     'cost' => 4500],
            ['cat' => 'Furniture',     'name' => 'Teacher Desk',              'brand' => 'Godrej',     'cost' => 6500],
            ['cat' => 'Furniture',     'name' => 'Office Chair',              'brand' => 'Featherlite','cost' => 3200],
            ['cat' => 'Furniture',     'name' => 'Filing Cabinet',            'brand' => 'Godrej',     'cost' => 8900],
            ['cat' => 'Furniture',     'name' => 'Whiteboard 6x4',            'brand' => 'Camlin',     'cost' => 2500],
            ['cat' => 'Furniture',     'name' => 'Library Bookshelf',         'brand' => 'Damro',      'cost' => 12500],
            ['cat' => 'IT Equipment',  'name' => 'Desktop Computer i5',       'brand' => 'HP',         'cost' => 42000],
            ['cat' => 'IT Equipment',  'name' => 'Laptop ThinkPad',           'brand' => 'Lenovo',     'cost' => 58000],
            ['cat' => 'IT Equipment',  'name' => 'Projector EB-X51',          'brand' => 'Epson',      'cost' => 38000],
            ['cat' => 'IT Equipment',  'name' => 'Laser Printer',             'brand' => 'HP',         'cost' => 14500],
            ['cat' => 'IT Equipment',  'name' => 'Network Switch 24-port',    'brand' => 'D-Link',     'cost' => 9500],
            ['cat' => 'IT Equipment',  'name' => 'Wi-Fi Router AC1750',       'brand' => 'TP-Link',    'cost' => 5500],
            ['cat' => 'IT Equipment',  'name' => 'Tablet Galaxy Tab',         'brand' => 'Samsung',    'cost' => 18900],
            ['cat' => 'Lab Equipment', 'name' => 'Compound Microscope',       'brand' => 'Olympus',    'cost' => 24000],
            ['cat' => 'Lab Equipment', 'name' => 'Bunsen Burner',             'brand' => 'Generic',    'cost' => 850],
            ['cat' => 'Lab Equipment', 'name' => 'Digital Balance',           'brand' => 'Sartorius',  'cost' => 12500],
            ['cat' => 'Lab Equipment', 'name' => 'Physics Pendulum Set',      'brand' => 'Generic',    'cost' => 3500],
            ['cat' => 'Lab Equipment', 'name' => 'Chemistry Glassware Set',   'brand' => 'Borosil',    'cost' => 6800],
            ['cat' => 'Lab Equipment', 'name' => 'Centrifuge',                'brand' => 'Remi',       'cost' => 28500],
            ['cat' => 'Sports',        'name' => 'Cricket Kit',               'brand' => 'SS',         'cost' => 8500],
            ['cat' => 'Sports',        'name' => 'Football',                  'brand' => 'Nivia',      'cost' => 1200],
            ['cat' => 'Sports',        'name' => 'Basketball',                'brand' => 'Nivia',      'cost' => 1500],
            ['cat' => 'Sports',        'name' => 'Badminton Net & Posts',     'brand' => 'Yonex',      'cost' => 4500],
            ['cat' => 'Sports',        'name' => 'Yoga Mats (Set of 30)',     'brand' => 'Adidas',     'cost' => 7500],
            ['cat' => 'Sports',        'name' => 'Athletic Track Equipment',  'brand' => 'Generic',    'cost' => 15000],
            ['cat' => 'Audio Visual',  'name' => 'Wireless Microphone',       'brand' => 'Shure',      'cost' => 18500],
            ['cat' => 'Audio Visual',  'name' => 'PA System Amplifier',       'brand' => 'Ahuja',      'cost' => 22000],
            ['cat' => 'Audio Visual',  'name' => 'Smart TV 55"',              'brand' => 'Samsung',    'cost' => 42000],
            ['cat' => 'Audio Visual',  'name' => 'Camera DSLR',               'brand' => 'Canon',      'cost' => 56000],
            ['cat' => 'Audio Visual',  'name' => 'Auditorium Speaker Set',    'brand' => 'JBL',        'cost' => 34500],
        ];

        $assetIds = [];
        foreach ($assets as $i => $a) {
            $purchaseDate = $now->copy()->subYears(rand(0, 4))->subDays(rand(0, 365));
            $status = ['available', 'available', 'available', 'assigned', 'under_maintenance'][array_rand(['available', 'available', 'available', 'assigned', 'under_maintenance'])];
            $assetIds[] = [
                'id' => DB::table('assets')->insertGetId([
                    'school_id'           => $schoolId,
                    'category_id'         => $categoryIds[$a['cat']],
                    'name'                => $a['name'],
                    'asset_code'          => 'AST' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                    'brand'               => $a['brand'],
                    'model_no'            => 'M-' . rand(100, 999),
                    'serial_no'           => 'SN' . rand(100000, 999999),
                    'purchase_date'       => $purchaseDate->format('Y-m-d'),
                    'purchase_cost'       => $a['cost'],
                    'supplier'            => null,
                    'warranty_until'      => $purchaseDate->copy()->addYears(2)->format('Y-m-d'),
                    'useful_life_years'   => rand(3, 8),
                    'depreciation_method' => 'straight_line',
                    'condition'           => ['excellent', 'good', 'good', 'fair'][array_rand(['excellent', 'good', 'good', 'fair'])],
                    'status'              => $status,
                    'notes'               => null,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]),
                'status' => $status,
            ];
        }

        // ── 3. Asset assignments (~10 of the 'assigned' ones) ─────────────────
        $deptIds = DB::table('departments')->where('school_id', $schoolId)->pluck('id')->toArray();
        $assignedAssets = array_filter($assetIds, fn($a) => $a['status'] === 'assigned');
        $count = 0;
        foreach ($assignedAssets as $a) {
            if ($count >= 10) break;
            DB::table('asset_assignments')->insert([
                'school_id'    => $schoolId,
                'asset_id'     => $a['id'],
                'assignee_type'=> 'department',
                'assignee_id'  => $deptIds ? $deptIds[array_rand($deptIds)] : null,
                'location'     => 'Block ' . chr(65 + rand(0, 3)) . ', Room ' . rand(101, 305),
                'assigned_on'  => $now->copy()->subDays(rand(30, 200))->format('Y-m-d'),
                'returned_on'  => null,
                'assigned_by'  => $adminUserId,
                'notes'        => null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
            $count++;
        }

        // ── 4. Maintenance entries (~5 records on misc assets) ────────────────
        $maintIssues = [
            'Display flickering, needs panel replacement',
            'Routine annual servicing',
            'Cracked casing — needs repair',
            'Bulb replacement required',
            'Calibration check needed',
        ];
        $sampleAssets = array_slice($assetIds, 0, 5);
        foreach ($sampleAssets as $i => $a) {
            $reported = $now->copy()->subDays(rand(10, 90));
            $isResolved = rand(0, 1) === 1;
            DB::table('asset_maintenance')->insert([
                'school_id'         => $schoolId,
                'asset_id'          => $a['id'],
                'reported_on'       => $reported->format('Y-m-d'),
                'issue_description' => $maintIssues[$i],
                'type'              => ['preventive', 'corrective', 'inspection'][array_rand(['preventive', 'corrective', 'inspection'])],
                'status'            => $isResolved ? 'resolved' : 'in_progress',
                'cost'              => rand(500, 8000),
                'resolved_on'       => $isResolved ? $reported->copy()->addDays(rand(2, 14))->format('Y-m-d') : null,
                'vendor'            => 'School Vendor Pvt Ltd',
                'resolution_notes'  => $isResolved ? 'Resolved by vendor; back in service.' : null,
                'reported_by'       => $adminUserId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }

        $this->command->info('✅ Inventory seeded: 5 categories, 30 assets, 10 assignments, 5 maintenance records.');
    }
}
