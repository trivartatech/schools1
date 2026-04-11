<?php

namespace App\Http\Controllers\School\Hostel;

use App\Http\Controllers\Controller;
use App\Models\HostelMessMenu;
use App\Models\Hostel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MessController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $query = HostelMessMenu::where('school_id', $schoolId)->with('hostel');

        if ($request->hostel_id) {
            $query->where('hostel_id', $request->hostel_id);
        }

        $menus = $query->paginate(50);
        $hostels = Hostel::where('school_id', $schoolId)->get(['id', 'name']);

        return Inertia::render('School/Hostel/Mess/Index', [
            'menus' => $menus,
            'hostels' => $hostels,
            'filters' => $request->only('hostel_id')
        ]);
    }

    public function storeMenu(Request $request)
    {
        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'hostel_id' => ['required', Rule::exists('hostels', 'id')->where('school_id', $schoolId)],
            'day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'meal_type' => 'required|string|in:Breakfast,Lunch,Snacks,Dinner',
            'items' => 'required|string'
        ]);

        $validated['school_id'] = $schoolId;

        HostelMessMenu::updateOrCreate(
            [
                'school_id' => $schoolId,
                'hostel_id' => $validated['hostel_id'],
                'day' => $validated['day'],
                'meal_type' => $validated['meal_type']
            ],
            ['items' => $validated['items']]
        );

        return back()->with('success', 'Mess menu saved successfully');
    }

    public function destroyMenu(HostelMessMenu $menu)
    {
        abort_if($menu->school_id !== app('current_school_id'), 403);

        $menu->delete();
        return back()->with('success', 'Menu deleted');
    }

    /**
     * Meal count report — how many veg/non-veg/custom students per hostel.
     */
    public function mealReport(Request $request)
    {
        $schoolId = app('current_school_id');
        $hostels  = Hostel::where('school_id', $schoolId)->get(['id', 'name']);

        $query = \App\Models\HostelStudent::where('school_id', $schoolId)
            ->where('status', 'Active')
            ->with(['student:id,first_name,last_name,admission_no', 'bed.room.hostel:id,name']);

        if ($request->hostel_id) {
            $query->whereHas('bed.room', fn($q) => $q->where('hostel_id', $request->hostel_id));
        }

        $allocations = $query->get();

        // Aggregate by hostel and mess_type
        $byHostel = [];
        foreach ($allocations as $a) {
            $hostelName = $a->bed?->room?->hostel?->name ?? 'Unknown';
            $hostelId   = $a->bed?->room?->hostel?->id ?? 0;
            if (!isset($byHostel[$hostelId])) {
                $byHostel[$hostelId] = [
                    'hostel_id' => $hostelId,
                    'hostel_name' => $hostelName,
                    'Veg' => 0, 'Non-Veg' => 0, 'Custom' => 0, 'None' => 0, 'total' => 0,
                    'students' => [],
                ];
            }
            $type = $a->mess_type ?? 'None';
            $byHostel[$hostelId][$type] = ($byHostel[$hostelId][$type] ?? 0) + 1;
            $byHostel[$hostelId]['total']++;
            $byHostel[$hostelId]['students'][] = [
                'name'         => $a->student?->first_name . ' ' . $a->student?->last_name,
                'admission_no' => $a->student?->admission_no,
                'mess_type'    => $type,
                'room'         => $a->bed?->room?->room_number,
                'bed'          => $a->bed?->name,
            ];
        }

        return Inertia::render('School/Hostel/Mess/MealReport', [
            'hostels'  => $hostels,
            'report'   => array_values($byHostel),
            'filters'  => $request->only('hostel_id'),
        ]);
    }
}
