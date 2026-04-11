<?php

namespace App\Http\Controllers\School\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\HostelRoom;
use App\Models\HostelBed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $query = HostelRoom::where('school_id', $schoolId)->with(['hostel', 'beds.student.student']);

        if ($request->hostel_id) {
            $query->where('hostel_id', $request->hostel_id);
        }

        $rooms = $query->paginate(20);
        $hostels = Hostel::where('school_id', $schoolId)->get(['id', 'name', 'blocks', 'floors', 'room_types']);

        return Inertia::render('School/Hostel/Rooms/Index', [
            'rooms' => $rooms,
            'hostels' => $hostels,
            'filters' => $request->only('hostel_id')
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'hostel_id' => ['required', Rule::exists('hostels', 'id')->where('school_id', $schoolId)],
            'block_name' => 'nullable|string|max:255',
            'floor_name' => 'nullable|string|max:255',
            'room_number' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1|max:20',
            'room_type' => 'nullable|string|max:255',
            'cost_per_month' => 'required|numeric|min:0',
        ]);

        $validated['school_id'] = $schoolId;

        DB::transaction(function () use ($validated, $schoolId) {
            $room = HostelRoom::create($validated);

            for ($i = 1; $i <= $validated['capacity']; $i++) {
                HostelBed::create([
                    'school_id' => $schoolId,
                    'hostel_room_id' => $room->id,
                    'name' => 'Bed ' . $i,
                    'status' => 'Available'
                ]);
            }
        });

        return back()->with('success', 'Room and beds created successfully');
    }

    public function update(Request $request, HostelRoom $room)
    {
        abort_if($room->school_id !== app('current_school_id'), 403);

        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'hostel_id' => ['required', Rule::exists('hostels', 'id')->where('school_id', $schoolId)],
            'block_name' => 'nullable|string|max:255',
            'floor_name' => 'nullable|string|max:255',
            'room_number' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1|max:20',
            'room_type' => 'nullable|string|max:255',
            'cost_per_month' => 'required|numeric|min:0',
            'status' => 'required|in:Available,Full,Maintenance'
        ]);

        $room->update($validated);
        return back()->with('success', 'Room updated successfully');
    }

    public function destroy(HostelRoom $room)
    {
        abort_if($room->school_id !== app('current_school_id'), 403);

        $room->delete();
        return back()->with('success', 'Room deleted successfully');
    }
}
