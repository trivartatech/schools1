<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class SchoolProfileController extends Controller
{
    /**
     * Display the school profile settings page.
     */
    public function index()
    {
        return Inertia::render('School/Settings/Profile', [
            'school' => app('current_school')
        ]);
    }

    /**
     * Update the school profile.
     */
    public function update(Request $request)
    {
        $school = app('current_school');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('schools')->ignore($school->id)],
            'board' => 'required|string|max:50',
            'affiliation_no' => 'nullable|string|max:100',
            'udise_code' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'principal_name' => 'nullable|string|max:255',
        ]);

        $school->update($validated);

        return redirect()->back()->with('status', 'School profile updated successfully.');
    }
}
