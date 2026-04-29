<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class StationarySeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        $academicYearId = DB::table('academic_years')->where('school_id', $schoolId)->where('is_current', true)->value('id')
                       ?? DB::table('academic_years')->where('school_id', $schoolId)->where('status', 'active')->value('id');

        $adminUserId = DB::table('users')->where('school_id', $schoolId)
            ->whereIn('user_type', ['principal', 'admin', 'school_admin'])
            ->value('id');

        Schema::disableForeignKeyConstraints();
        DB::table('stationary_issuances')->where('school_id', $schoolId)->delete();
        DB::table('stationary_allocation_items')->whereIn('allocation_id',
            DB::table('stationary_student_allocation')->where('school_id', $schoolId)->pluck('id'))->delete();
        DB::table('stationary_student_allocation')->where('school_id', $schoolId)->delete();
        DB::table('stationary_items')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // ── 1. Stationary items master ────────────────────────────────────────
        $items = [
            ['name' => 'Notebook 200 pages',  'code' => 'NB-200', 'price' => 60,  'stock' => 500],
            ['name' => 'Notebook 400 pages',  'code' => 'NB-400', 'price' => 110, 'stock' => 300],
            ['name' => 'Drawing Book A4',     'code' => 'DR-A4',  'price' => 90,  'stock' => 200],
            ['name' => 'Geometry Box',        'code' => 'GEO-01', 'price' => 250, 'stock' => 150],
            ['name' => 'Pen Pack (10)',       'code' => 'PEN-10', 'price' => 100, 'stock' => 400],
            ['name' => 'Pencil Pack (10)',    'code' => 'PCL-10', 'price' => 50,  'stock' => 600],
            ['name' => 'Eraser Pack (5)',     'code' => 'ER-5',   'price' => 25,  'stock' => 800],
            ['name' => 'Sharpener Pack (3)',  'code' => 'SH-3',   'price' => 30,  'stock' => 700],
            ['name' => 'Math Textbook',       'code' => 'TB-MAT', 'price' => 320, 'stock' => 150],
            ['name' => 'Science Textbook',    'code' => 'TB-SCI', 'price' => 340, 'stock' => 150],
            ['name' => 'English Textbook',    'code' => 'TB-ENG', 'price' => 280, 'stock' => 150],
            ['name' => 'School Diary',        'code' => 'DIARY',  'price' => 150, 'stock' => 400],
            ['name' => 'Crayons (24 colours)','code' => 'CRY-24', 'price' => 220, 'stock' => 120],
            ['name' => 'A4 File Folder',      'code' => 'FF-A4',  'price' => 40,  'stock' => 350],
            ['name' => 'Lunch Box',           'code' => 'LB-01',  'price' => 380, 'stock' => 80],
        ];

        $itemRows = [];
        foreach ($items as $it) {
            $itemRows[$it['code']] = [
                'school_id'     => $schoolId,
                'name'          => $it['name'],
                'code'          => $it['code'],
                'unit_price'    => $it['price'],
                'hsn_code'      => '4901',
                'current_stock' => $it['stock'],
                'min_stock'     => 30,
                'status'        => 'active',
                'description'   => null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        $itemIdMap = [];
        foreach ($itemRows as $code => $row) {
            $itemIdMap[$code] = DB::table('stationary_items')->insertGetId($row);
        }

        // ── 2. Allocations for the first 30 students ──────────────────────────
        $studentIds = DB::table('students')->where('school_id', $schoolId)->limit(30)->pluck('id')->toArray();

        if (empty($studentIds)) {
            $this->command->info('StationarySeeder: no students; only items seeded.');
            return;
        }

        // A "kit" of 6 standard items for each student
        $kitItems = ['NB-200', 'PEN-10', 'PCL-10', 'ER-5', 'SH-3', 'DIARY'];
        $kitQty   = ['NB-200' => 4, 'PEN-10' => 1, 'PCL-10' => 1, 'ER-5' => 1, 'SH-3' => 1, 'DIARY' => 1];

        foreach ($studentIds as $sid) {
            $totalAmount = 0;
            $lineRows = [];
            foreach ($kitItems as $code) {
                $qty       = $kitQty[$code];
                $unitPrice = $itemRows[$code]['unit_price'];
                $lineTotal = $qty * $unitPrice;
                $totalAmount += $lineTotal;

                $lineRows[] = [
                    'item_id'       => $itemIdMap[$code],
                    'qty_entitled'  => $qty,
                    'qty_collected' => $qty, // assume fully collected for demo
                    'unit_price'    => $unitPrice,
                    'line_total'    => $lineTotal,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            $paid    = rand(0, 100) < 70 ? $totalAmount : ($totalAmount * 0.5);
            $balance = $totalAmount - $paid;
            $payStatus = $balance == 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

            $allocId = DB::table('stationary_student_allocation')->insertGetId([
                'school_id'         => $schoolId,
                'student_id'        => $sid,
                'academic_year_id'  => $academicYearId,
                'total_amount'      => $totalAmount,
                'amount_paid'       => $paid,
                'discount'          => 0,
                'fine'              => 0,
                'balance'           => $balance,
                'payment_status'    => $payStatus,
                'collection_status' => 'complete',
                'last_payment_date' => $paid > 0 ? $now->copy()->subDays(rand(1, 30))->format('Y-m-d') : null,
                'last_issued_date'  => $now->copy()->subDays(rand(1, 30))->format('Y-m-d'),
                'status'            => 'active',
                'remarks'           => null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            // Insert line items with the new allocation_id
            foreach ($lineRows as &$line) {
                $line['allocation_id'] = $allocId;
            }
            DB::table('stationary_allocation_items')->insert($lineRows);

            // Issuance event: one event recording the handover
            DB::table('stationary_issuances')->insert([
                'school_id'     => $schoolId,
                'allocation_id' => $allocId,
                'student_id'    => $sid,
                'issued_by'     => $adminUserId,
                'issued_at'     => $now->copy()->subDays(rand(1, 30)),
                'remarks'       => 'Standard kit handover at term start.',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        $this->command->info('✅ Stationary seeded: ' . count($items) . ' items, ' . count($studentIds) . ' allocations + issuances.');
    }
}
