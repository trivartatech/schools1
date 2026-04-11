<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Holiday;

class HolidayController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school')->id;
        $holidays = Holiday::where('school_id', $schoolId)->orderBy('date')->get();

        return Inertia::render('School/Schedule/Holidays', [
            'holidays' => $holidays
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school')->id;

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'date'        => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:date',
            'type'        => 'required|in:holiday,event,exam,other',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['school_id'] = $schoolId;
        Holiday::create($validated);

        return redirect()->back()->with('status', 'Holiday/Event created successfully.');
    }

    public function update(Request $request, Holiday $holiday)
    {
        if ($holiday->school_id !== app('current_school')->id) abort(403);

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'date'        => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:date',
            'type'        => 'required|in:holiday,event,exam,other',
            'description' => 'nullable|string|max:1000',
        ]);

        $holiday->update($validated);

        return redirect()->back()->with('status', 'Holiday/Event updated successfully.');
    }

    public function destroy(Holiday $holiday)
    {
        if ($holiday->school_id !== app('current_school')->id) abort(403);
        $holiday->delete();

        return redirect()->back()->with('status', 'Holiday/Event deleted successfully.');
    }
}
