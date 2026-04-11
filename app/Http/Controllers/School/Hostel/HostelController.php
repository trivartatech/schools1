<?php

namespace App\Http\Controllers\School\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class HostelController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');
        $hostels = Hostel::where('school_id', $schoolId)->with('warden')->get();

        return Inertia::render('School/Hostel/Hostels/Index', [
            'hostels' => $hostels,
            'users' => User::where('school_id', $schoolId)->whereNotIn('user_type', ['student', 'parent'])->get(['id', 'name'])
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:Boys,Girls,Co-ed',
            'warden_id' => ['nullable', Rule::exists('users', 'id')->where('school_id', $schoolId)],
            'intake_capacity' => 'nullable|integer',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'blocks' => 'nullable|array',
            'floors' => 'nullable|array',
            'room_types' => 'nullable|array'
        ]);

        $validated['school_id'] = $schoolId;
        $validated['intake_capacity'] = $validated['intake_capacity'] ?? 0;
        Hostel::create($validated);

        return back()->with('success', 'Hostel created successfully');
    }

    public function update(Request $request, Hostel $hostel)
    {
        abort_if($hostel->school_id !== app('current_school_id'), 403);

        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:Boys,Girls,Co-ed',
            'warden_id' => ['nullable', Rule::exists('users', 'id')->where('school_id', $schoolId)],
            'intake_capacity' => 'nullable|integer',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'blocks' => 'nullable|array',
            'floors' => 'nullable|array',
            'room_types' => 'nullable|array'
        ]);

        $validated['intake_capacity'] = $validated['intake_capacity'] ?? 0;
        $hostel->update($validated);
        return back()->with('success', 'Hostel updated successfully');
    }

    public function destroy(Hostel $hostel)
    {
        abort_if($hostel->school_id !== app('current_school_id'), 403);

        $hostel->delete();
        return back()->with('success', 'Hostel deleted successfully');
    }
}
