<?php

namespace App\Http\Controllers\School\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\HostelRoom;
use App\Models\HostelBed;
use App\Models\HostelStudent;
use App\Models\HostelLeaveRequest;
use App\Models\HostelComplaint;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');

        // Auto-expire gate passes past to_date
        HostelLeaveRequest::where('school_id', $schoolId)
            ->whereIn('status', ['Approved', 'Out'])
            ->where('to_date', '<', now()->toDateString())
            ->where('is_expired', false)
            ->update(['is_expired' => true]);

        $stats = [
            'total_beds'       => HostelBed::where('school_id', $schoolId)->count(),
            'occupied_beds'    => HostelBed::where('school_id', $schoolId)->where('status', 'Occupied')->count(),
            'active_students'  => HostelStudent::where('school_id', $schoolId)->where('status', 'Active')->count(),
            'pending_leaves'   => HostelLeaveRequest::where('school_id', $schoolId)->where('status', 'Pending')->count(),
            'students_on_leave'=> HostelLeaveRequest::where('school_id', $schoolId)->where('status', 'Out')->count(),
            'open_complaints'  => HostelComplaint::where('school_id', $schoolId)->whereIn('status', ['open', 'in_progress'])->count(),
        ];
        $stats['available_beds'] = $stats['total_beds'] - $stats['occupied_beds'];

        // Occupancy by hostel with block/floor breakdown
        $hostels = Hostel::where('school_id', $schoolId)->with(['rooms.beds'])->get();

        $occupancy = [];
        foreach ($hostels as $hostel) {
            $hostelData = [
                'id'   => $hostel->id,
                'name' => $hostel->name,
                'type' => $hostel->type,
                'blocks' => [],
                'total_beds'    => 0,
                'occupied_beds' => 0,
            ];

            $byBlock = [];
            foreach ($hostel->rooms as $room) {
                $block = $room->block_name ?: 'Main';
                $floor = $room->floor_name ?: 'Ground';

                if (!isset($byBlock[$block])) $byBlock[$block] = [];
                if (!isset($byBlock[$block][$floor])) $byBlock[$block][$floor] = ['rooms' => []];

                $beds = $room->beds;
                $total    = $beds->count();
                $occupied = $beds->where('status', 'Occupied')->count();

                $hostelData['total_beds']    += $total;
                $hostelData['occupied_beds'] += $occupied;

                $byBlock[$block][$floor]['rooms'][] = [
                    'id'          => $room->id,
                    'room_number' => $room->room_number,
                    'room_type'   => $room->room_type,
                    'capacity'    => $room->capacity,
                    'total_beds'  => $total,
                    'occupied'    => $occupied,
                    'available'   => $total - $occupied,
                    'status'      => $room->status,
                ];
            }

            // Convert to array format
            foreach ($byBlock as $blockName => $floors) {
                $blockArr = ['name' => $blockName, 'floors' => []];
                foreach ($floors as $floorName => $data) {
                    $blockArr['floors'][] = ['name' => $floorName, 'rooms' => $data['rooms']];
                }
                $hostelData['blocks'][] = $blockArr;
            }

            $occupancy[] = $hostelData;
        }

        return Inertia::render('School/Hostel/Dashboard', [
            'stats'     => $stats,
            'occupancy' => $occupancy,
        ]);
    }
}
