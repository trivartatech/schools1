<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ItemStoreSeeder extends Seeder
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
        DB::table('store_transactions')->where('school_id', $schoolId)->delete();
        DB::table('store_items')->where('school_id', $schoolId)->delete();
        DB::table('item_stores')->where('school_id', $schoolId)->delete();
        DB::table('suppliers')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // ── 1. Suppliers ──────────────────────────────────────────────────────
        $suppliers = [
            ['name' => 'Modern Stationers Pvt Ltd',  'contact_person' => 'Rakesh Sharma', 'phone' => '9876543210', 'email' => 'sales@modernstationers.com', 'gstin' => '07AAACM1234A1Z5', 'city' => 'New Delhi',  'state' => 'Delhi'],
            ['name' => 'Educational Supplies Co.',    'contact_person' => 'Priya Mehta',   'phone' => '9123456789', 'email' => 'orders@edusupplies.in',     'gstin' => '27BBBED5678B1Z3', 'city' => 'Mumbai',     'state' => 'Maharashtra'],
            ['name' => 'TechWorld Computer Solutions','contact_person' => 'Karan Verma',   'phone' => '9988776655', 'email' => 'b2b@techworld.in',          'gstin' => '29CCCTE9012C1Z9', 'city' => 'Bengaluru',  'state' => 'Karnataka'],
        ];
        $supplierIds = [];
        foreach ($suppliers as $s) {
            $supplierIds[] = DB::table('suppliers')->insertGetId([
                'school_id'      => $schoolId,
                'name'           => $s['name'],
                'contact_person' => $s['contact_person'],
                'phone'          => $s['phone'],
                'email'          => $s['email'],
                'gstin'          => $s['gstin'],
                'address'        => '123, Industrial Area, ' . $s['city'],
                'city'           => $s['city'],
                'state'          => $s['state'],
                'website'        => null,
                'notes'          => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        // ── 2. One central store ──────────────────────────────────────────────
        $storeId = DB::table('item_stores')->insertGetId([
            'school_id'         => $schoolId,
            'name'              => 'Main Stationery Store',
            'location'          => 'Block A, Ground Floor',
            'incharge_staff_id' => $adminUserId,
            'description'       => 'Central inventory store for school stationery and supplies.',
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        // ── 3. Store items (~20) ──────────────────────────────────────────────
        $items = [
            ['name' => 'A4 Bond Paper Ream',     'unit' => 'ream',  'qty' => 80,  'min' => 20, 'price' => 280],
            ['name' => 'Whiteboard Marker Box',  'unit' => 'box',   'qty' => 50,  'min' => 10, 'price' => 320],
            ['name' => 'Chalk Box (white)',      'unit' => 'box',   'qty' => 100, 'min' => 30, 'price' => 80],
            ['name' => 'Chalk Box (coloured)',   'unit' => 'box',   'qty' => 60,  'min' => 20, 'price' => 120],
            ['name' => 'Stapler Pin Box',         'unit' => 'box',   'qty' => 200, 'min' => 50, 'price' => 25],
            ['name' => 'Cellophane Tape',         'unit' => 'roll',  'qty' => 90,  'min' => 30, 'price' => 35],
            ['name' => 'File Folder',             'unit' => 'pcs',   'qty' => 250, 'min' => 60, 'price' => 40],
            ['name' => 'Punch Machine',           'unit' => 'pcs',   'qty' => 15,  'min' => 3,  'price' => 350],
            ['name' => 'Stapler',                 'unit' => 'pcs',   'qty' => 25,  'min' => 5,  'price' => 250],
            ['name' => 'Toner Cartridge HP',      'unit' => 'pcs',   'qty' => 12,  'min' => 4,  'price' => 4500],
            ['name' => 'Surge Protector Strip',   'unit' => 'pcs',   'qty' => 30,  'min' => 8,  'price' => 850],
            ['name' => 'LAN Cable Cat6 (m)',      'unit' => 'meter', 'qty' => 120, 'min' => 30, 'price' => 25],
            ['name' => 'First Aid Kit',           'unit' => 'pcs',   'qty' => 8,   'min' => 3,  'price' => 1200],
            ['name' => 'Sanitizer 500ml',         'unit' => 'pcs',   'qty' => 60,  'min' => 15, 'price' => 180],
            ['name' => 'Garbage Bag Roll',        'unit' => 'roll',  'qty' => 40,  'min' => 10, 'price' => 60],
            ['name' => 'Toilet Paper Pack (12)',  'unit' => 'pack',  'qty' => 35,  'min' => 10, 'price' => 380],
            ['name' => 'Floor Cleaner 5L',        'unit' => 'litre', 'qty' => 45,  'min' => 10, 'price' => 220],
            ['name' => 'Hand Soap Dispenser',     'unit' => 'pcs',   'qty' => 20,  'min' => 5,  'price' => 320],
            ['name' => 'Stationery Box (Class)',  'unit' => 'pcs',   'qty' => 30,  'min' => 8,  'price' => 850],
            ['name' => 'Cleaning Mop',            'unit' => 'pcs',   'qty' => 18,  'min' => 5,  'price' => 220],
        ];

        $itemIds = [];
        foreach ($items as $it) {
            $itemIds[] = DB::table('store_items')->insertGetId([
                'school_id'    => $schoolId,
                'store_id'     => $storeId,
                'supplier_id'  => $supplierIds[array_rand($supplierIds)],
                'name'         => $it['name'],
                'unit'         => $it['unit'],
                'quantity'     => $it['qty'],
                'min_quantity' => $it['min'],
                'unit_price'   => $it['price'],
                'notes'        => null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // ── 4. Transactions (10 in/out events) ────────────────────────────────
        for ($i = 0; $i < 10; $i++) {
            $itemId  = $itemIds[array_rand($itemIds)];
            $type    = rand(0, 100) < 60 ? 'out' : 'in'; // mostly issuance
            $qty     = rand(1, 20);
            $txnDate = $now->copy()->subDays(rand(1, 30));

            DB::table('store_transactions')->insert([
                'school_id'        => $schoolId,
                'store_id'         => $storeId,
                'item_id'          => $itemId,
                'type'             => $type,
                'quantity'         => $qty,
                'reference'        => $type === 'in' ? 'PO-' . rand(1000, 9999) : 'REQ-' . rand(1000, 9999),
                'notes'            => $type === 'in' ? 'Stock replenishment' : 'Issued to department',
                'created_by'       => $adminUserId,
                'transaction_date' => $txnDate->format('Y-m-d'),
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        $this->command->info('✅ Item store seeded: 3 suppliers, 1 store, 20 items, 10 transactions.');
    }
}
