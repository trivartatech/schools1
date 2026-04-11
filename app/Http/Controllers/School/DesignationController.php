<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DesignationController extends Controller
{
    public function index()
    {
        $designations = Designation::tenant()->with('parent')->latest()->get();
        return Inertia::render('School/Staff/Designations', [
            'designations' => $designations
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => ['nullable', Rule::exists('designations', 'id')->where('school_id', app('current_school_id'))],
        ]);

        $validated['school_id'] = app('current_school_id');

        Designation::create($validated);

        return back()->with('success', 'Designation created successfully.');
    }

    public function update(Request $request, Designation $designation)
    {
        abort_unless($designation->school_id === app('current_school_id'), 403, 'Unauthorized access.');
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => ['nullable', Rule::exists('designations', 'id')->where('school_id', app('current_school_id'))],
        ]);

        $designation->update($validated);

        return back()->with('success', 'Designation updated successfully.');
    }
    
    public function toggle(Designation $designation)
    {
        abort_unless($designation->school_id === app('current_school_id'), 403, 'Unauthorized access.');

        $designation->update([
            'is_active' => !$designation->is_active
        ]);
        return back()->with('success', 'Designation status updated.');
    }

    public function destroy(Designation $designation)
    {
        abort_unless($designation->school_id === app('current_school_id'), 403, 'Unauthorized access.');

        if ($designation->staff()->exists()) {
            return back()->withErrors(['message' => 'Cannot delete designation currently in use by staff members.']);
        }
        
        $designation->delete();
        return back()->with('success', 'Designation deleted successfully.');
    }
}
