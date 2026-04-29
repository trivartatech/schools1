<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class TransportSeeder extends Seeder
{
    public function run(): void
    {
        $school = DB::table('schools')->first();
        $schoolId = $school->id;
        $now = Carbon::now();

        // Clear existing transport data
        Schema::disableForeignKeyConstraints();
        // gps_logs and live_locations scope by vehicle_id, not school_id
        $vehicleIds = DB::table('transport_vehicles')->where('school_id', $schoolId)->pluck('id');
        DB::table('transport_student_allocation')->where('school_id', $schoolId)->delete();
        DB::table('transport_gps_logs')->whereIn('vehicle_id', $vehicleIds)->delete();
        DB::table('transport_vehicle_live_locations')->whereIn('vehicle_id', $vehicleIds)->delete();
        DB::table('transport_vehicles')->where('school_id', $schoolId)->delete();
        DB::table('transport_stops')->where('school_id', $schoolId)->delete();
        DB::table('transport_routes')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        // ── 1. Routes (Chitradurga district, Karnataka) ───────────────────────
        $schoolEnd = 'School Campus, Chitradurga';
        $routesData = [
            ['route_name' => 'Route A - Davanagere Road', 'route_code' => 'RT-A', 'start_location' => 'Joladahalli Circle',     'end_location' => $schoolEnd, 'distance' => 12.5, 'estimated_time' => '40 mins'],
            ['route_name' => 'Route B - Hiriyur Road',     'route_code' => 'RT-B', 'start_location' => 'Aimangala Village',      'end_location' => $schoolEnd, 'distance' => 18.0, 'estimated_time' => '55 mins'],
            ['route_name' => 'Route C - Holalkere Road',   'route_code' => 'RT-C', 'start_location' => 'Mayakonda',              'end_location' => $schoolEnd, 'distance' => 9.2,  'estimated_time' => '30 mins'],
            ['route_name' => 'Route D - Bellary Road',     'route_code' => 'RT-D', 'start_location' => 'Sugur Cross',            'end_location' => $schoolEnd, 'distance' => 14.0, 'estimated_time' => '45 mins'],
            ['route_name' => 'Route E - City Inner Ring',  'route_code' => 'RT-E', 'start_location' => 'Gandhi Nagar Bus Stand', 'end_location' => $schoolEnd, 'distance' => 7.8,  'estimated_time' => '25 mins'],
        ];

        $routeIds = [];
        foreach ($routesData as $r) {
            $routeIds[$r['route_code']] = DB::table('transport_routes')->insertGetId(array_merge($r, [
                'school_id' => $schoolId,
                'status'    => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ]));
        }

        // ── 2. Stops per Route (Chitradurga district lat/lng cluster ~14.22 N, 76.40 E) ──
        $stopsPerRoute = [
            // RT-A: Davanagere Road (NE)
            'RT-A' => [
                ['stop_name' => 'Joladahalli Circle',         'pickup_time' => '06:45:00', 'drop_time' => '15:30:00', 'distance_from_school' => 12.5, 'fee' => 2400, 'stop_order' => 1, 'lat' => 14.2820, 'lng' => 76.4510],
                ['stop_name' => 'Medehalli Junction',         'pickup_time' => '06:55:00', 'drop_time' => '15:20:00', 'distance_from_school' => 10.2, 'fee' => 2200, 'stop_order' => 2, 'lat' => 14.2685, 'lng' => 76.4380],
                ['stop_name' => 'Onake Obavva Stadium Gate',   'pickup_time' => '07:05:00', 'drop_time' => '15:10:00', 'distance_from_school' => 7.8,  'fee' => 2000, 'stop_order' => 3, 'lat' => 14.2510, 'lng' => 76.4260],
                ['stop_name' => 'Fort Road Crossing',          'pickup_time' => '07:15:00', 'drop_time' => '15:00:00', 'distance_from_school' => 5.5,  'fee' => 1800, 'stop_order' => 4, 'lat' => 14.2370, 'lng' => 76.4150],
            ],
            // RT-B: Hiriyur Road (S, NH-48)
            'RT-B' => [
                ['stop_name' => 'Aimangala Village',           'pickup_time' => '06:30:00', 'drop_time' => '15:45:00', 'distance_from_school' => 18.0, 'fee' => 2800, 'stop_order' => 1, 'lat' => 14.0820, 'lng' => 76.4420],
                ['stop_name' => 'Bharamasagara Bus Stand',      'pickup_time' => '06:42:00', 'drop_time' => '15:35:00', 'distance_from_school' => 15.5, 'fee' => 2600, 'stop_order' => 2, 'lat' => 14.1180, 'lng' => 76.4360],
                ['stop_name' => 'Turuvanur Junction',           'pickup_time' => '06:52:00', 'drop_time' => '15:25:00', 'distance_from_school' => 12.0, 'fee' => 2400, 'stop_order' => 3, 'lat' => 14.1520, 'lng' => 76.4280],
                ['stop_name' => 'Hiriyur Road Toll',            'pickup_time' => '07:05:00', 'drop_time' => '15:12:00', 'distance_from_school' => 8.0,  'fee' => 2000, 'stop_order' => 4, 'lat' => 14.1820, 'lng' => 76.4180],
            ],
            // RT-C: Holalkere Road (W)
            'RT-C' => [
                ['stop_name' => 'Mayakonda Village',            'pickup_time' => '07:00:00', 'drop_time' => '15:25:00', 'distance_from_school' => 9.2,  'fee' => 2000, 'stop_order' => 1, 'lat' => 14.2280, 'lng' => 76.3160],
                ['stop_name' => 'Holalkere Road Cross',         'pickup_time' => '07:10:00', 'drop_time' => '15:15:00', 'distance_from_school' => 6.8,  'fee' => 1800, 'stop_order' => 2, 'lat' => 14.2240, 'lng' => 76.3450],
                ['stop_name' => 'Gowtham Hills Layout',         'pickup_time' => '07:18:00', 'drop_time' => '15:07:00', 'distance_from_school' => 4.5,  'fee' => 1600, 'stop_order' => 3, 'lat' => 14.2210, 'lng' => 76.3720],
            ],
            // RT-D: Bellary Road (N)
            'RT-D' => [
                ['stop_name' => 'Sugur Cross',                  'pickup_time' => '06:40:00', 'drop_time' => '15:40:00', 'distance_from_school' => 14.0, 'fee' => 2600, 'stop_order' => 1, 'lat' => 14.3380, 'lng' => 76.4420],
                ['stop_name' => 'Hampapatna Gate',              'pickup_time' => '06:50:00', 'drop_time' => '15:30:00', 'distance_from_school' => 11.5, 'fee' => 2400, 'stop_order' => 2, 'lat' => 14.3120, 'lng' => 76.4350],
                ['stop_name' => 'Challakere Road Junction',     'pickup_time' => '07:02:00', 'drop_time' => '15:18:00', 'distance_from_school' => 8.0,  'fee' => 2000, 'stop_order' => 3, 'lat' => 14.2820, 'lng' => 76.4250],
                ['stop_name' => 'Bharamapuram Layout',          'pickup_time' => '07:12:00', 'drop_time' => '15:08:00', 'distance_from_school' => 5.5,  'fee' => 1800, 'stop_order' => 4, 'lat' => 14.2580, 'lng' => 76.4180],
            ],
            // RT-E: City Inner Ring
            'RT-E' => [
                ['stop_name' => 'Gandhi Nagar Bus Stand',       'pickup_time' => '07:10:00', 'drop_time' => '15:20:00', 'distance_from_school' => 7.8,  'fee' => 1800, 'stop_order' => 1, 'lat' => 14.2330, 'lng' => 76.3950],
                ['stop_name' => 'Santhe Bagilu Market Gate',    'pickup_time' => '07:18:00', 'drop_time' => '15:12:00', 'distance_from_school' => 5.2,  'fee' => 1600, 'stop_order' => 2, 'lat' => 14.2280, 'lng' => 76.4010],
                ['stop_name' => 'IB Road Crossing',             'pickup_time' => '07:25:00', 'drop_time' => '15:05:00', 'distance_from_school' => 3.0,  'fee' => 1400, 'stop_order' => 3, 'lat' => 14.2250, 'lng' => 76.4055],
            ],
        ];

        $stopIds = []; // [route_code][stop_order] => stop_id
        foreach ($stopsPerRoute as $routeCode => $stops) {
            $routeId = $routeIds[$routeCode];
            $stopIds[$routeCode] = [];
            foreach ($stops as $stop) {
                $id = DB::table('transport_stops')->insertGetId([
                    'school_id'            => $schoolId,
                    'route_id'             => $routeId,
                    'stop_name'            => $stop['stop_name'],
                    'stop_code'            => $routeCode . '-S' . $stop['stop_order'],
                    'pickup_time'          => $stop['pickup_time'],
                    'drop_time'            => $stop['drop_time'],
                    'distance_from_school' => $stop['distance_from_school'],
                    'fee'                  => $stop['fee'],
                    'stop_order'           => $stop['stop_order'],
                    'latitude'             => $stop['lat'],
                    'longitude'            => $stop['lng'],
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);
                $stopIds[$routeCode][$stop['stop_order']] = $id;
            }
        }

        // ── 3. Vehicles ────────────────────────────────────────────────────────
        // Get driver-designation staff (we'll use any available staff as drivers)
        $staffIds = DB::table('staff')->where('school_id', $schoolId)->pluck('id')->toArray();

        // KA 16 = Chitradurga RTO registration prefix
        $vehiclesData = [
            ['vehicle_number' => 'KA 16 A 0001', 'vehicle_name' => 'Tata Starbus Ultra 52',  'capacity' => 52, 'route_code' => 'RT-A', 'conductor_name' => 'Manjunath G',   'gps' => 'GPS-A1', 'insurance_expiry' => '2026-12-31', 'fitness_expiry' => '2026-08-15', 'pollution_expiry' => '2026-05-10'],
            ['vehicle_number' => 'KA 16 A 0002', 'vehicle_name' => 'Eicher Skyline Pro 35',  'capacity' => 35, 'route_code' => 'RT-B', 'conductor_name' => 'Venkatesh M',   'gps' => 'GPS-B1', 'insurance_expiry' => '2026-11-30', 'fitness_expiry' => '2026-07-20', 'pollution_expiry' => '2026-04-25'],
            ['vehicle_number' => 'KA 16 B 0003', 'vehicle_name' => 'Tata LP 909 Mini Bus',   'capacity' => 30, 'route_code' => 'RT-C', 'conductor_name' => 'Nagaraj K',     'gps' => 'GPS-C1', 'insurance_expiry' => '2026-10-15', 'fitness_expiry' => '2026-09-30', 'pollution_expiry' => '2026-06-05'],
            ['vehicle_number' => 'KA 16 B 0004', 'vehicle_name' => 'Tata Starbus Ultra 45',  'capacity' => 45, 'route_code' => 'RT-D', 'conductor_name' => 'Basavaraj N',   'gps' => 'GPS-D1', 'insurance_expiry' => '2027-01-31', 'fitness_expiry' => '2026-06-10', 'pollution_expiry' => '2026-07-15'],
            ['vehicle_number' => 'KA 16 C 0005', 'vehicle_name' => 'Force Traveller 26 STD', 'capacity' => 26, 'route_code' => 'RT-E', 'conductor_name' => 'Shivakumar R',  'gps' => 'GPS-E1', 'insurance_expiry' => '2026-09-30', 'fitness_expiry' => '2026-10-20', 'pollution_expiry' => '2026-08-01'],
        ];

        $vehicleIds = []; // route_code => vehicle_id
        foreach ($vehiclesData as $idx => $v) {
            $driverId = !empty($staffIds) ? $staffIds[$idx % count($staffIds)] : null;
            $vehicleIds[$v['route_code']] = DB::table('transport_vehicles')->insertGetId([
                'school_id'        => $schoolId,
                'vehicle_number'   => $v['vehicle_number'],
                'vehicle_name'     => $v['vehicle_name'],
                'driver_id'        => $driverId,
                'conductor_name'   => $v['conductor_name'],
                'capacity'         => $v['capacity'],
                'route_id'         => $routeIds[$v['route_code']],
                'gps_device_id'    => $v['gps'],
                'insurance_expiry' => $v['insurance_expiry'],
                'fitness_expiry'   => $v['fitness_expiry'],
                'pollution_expiry' => $v['pollution_expiry'],
                'status'           => 'active',
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // ── 4. Student Allocations ─────────────────────────────────────────────
        // Get all students with active academic history
        $academicYearId = DB::table('academic_years')->where('school_id', $schoolId)->where('status', 'active')->value('id');
        $students = DB::table('student_academic_histories')
            ->where('academic_year_id', $academicYearId)
            ->pluck('student_id')
            ->toArray();

        $routeCodes = array_keys($routeIds);
        $allocated = 0;

        // Allocate ~60% of students to transport
        $toAllocate = array_slice($students, 0, (int)(count($students) * 0.6));

        foreach ($toAllocate as $idx => $studentId) {
            $routeCode = $routeCodes[$idx % count($routeCodes)];
            $routeId   = $routeIds[$routeCode];
            $vehicleId = $vehicleIds[$routeCode];

            // Pick a stop on this route
            $routeStops = $stopIds[$routeCode];
            $stopOrder  = array_keys($routeStops)[($idx) % count($routeStops)];
            $stopId     = $routeStops[$stopOrder];
            $stopFee    = $stopsPerRoute[$routeCode][$stopOrder - 1]['fee'];

            // Skip if already allocated
            $exists = DB::table('transport_student_allocation')
                ->where('student_id', $studentId)
                ->where('school_id', $schoolId)
                ->exists();
            if ($exists) continue;

            DB::table('transport_student_allocation')->insert([
                'school_id'     => $schoolId,
                'student_id'    => $studentId,
                'route_id'      => $routeId,
                'stop_id'       => $stopId,
                'vehicle_id'    => $vehicleId,
                'transport_fee' => $stopFee,
                'pickup_type'   => 'both',
                'start_date'    => '2026-04-01',
                'end_date'      => '2027-03-31',
                'status'        => 'active',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $allocated++;
        }

        $this->command->info('✅ Transport Module seeded successfully!');
        $this->command->info('   - ' . count($routesData) . ' Routes');
        $this->command->info('   - ' . array_sum(array_map('count', $stopsPerRoute)) . ' Stops');
        $this->command->info('   - ' . count($vehiclesData) . ' Vehicles');
        $this->command->info("   - {$allocated} Student Allocations (~60% of students)");
    }
}
