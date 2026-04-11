<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Period;

class PeriodController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_if(!$user->can('view_schedule') && !$user->isSchoolManagement(), 403, 'You do not have permission to view periods.');

        $schoolId = app('current_school_id');
        $periods = Period::where('school_id', $schoolId)->orderBy('order')->get();

        return Inertia::render('School/Schedule/Periods', [
            'periods' => $periods
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->can('manage_schedule') && !$user->isSchoolManagement(), 403, 'You do not have permission to manage periods.');

        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'type'       => 'required|in:period,break,lunch,assembly',
            'is_weekend' => 'boolean',
            'order'      => 'nullable|integer|min:1',
        ]);

        $validated['school_id'] = $schoolId;
        $validated['is_weekend'] = $request->boolean('is_weekend');
        Period::create($validated);

        return redirect()->back()->with('status', 'Period created successfully.');
    }

    public function update(Request $request, Period $period)
    {
        abort_if($period->school_id !== app('current_school_id'), 403);

        $user = auth()->user();
        abort_if(!$user->can('manage_schedule') && !$user->isSchoolManagement(), 403, 'You do not have permission to manage periods.');

        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'type'       => 'required|in:period,break,lunch,assembly',
            'is_weekend' => 'boolean',
            'order'      => 'nullable|integer|min:1',
        ]);

        $validated['is_weekend'] = $request->boolean('is_weekend');
        $period->update($validated);

        return redirect()->back()->with('status', 'Period updated successfully.');
    }

    public function destroy(Period $period)
    {
        abort_if($period->school_id !== app('current_school_id'), 403);

        $user = auth()->user();
        abort_if(!$user->can('manage_schedule') && !$user->isSchoolManagement(), 403, 'You do not have permission to manage periods.');

        $period->delete();

        return redirect()->back()->with('status', 'Period deleted successfully.');
    }
}
