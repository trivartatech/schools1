<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemConfigController extends Controller
{
    /**
     * Display the System Configuration page.
     */
    public function index()
    {
        $school = app('current_school');
        $settings = $school->settings ?? [];

        return Inertia::render('School/Settings/SystemConfig', [
            'school'   => $school,
            'settings' => $settings,
        ]);
    }

    /**
     * Update school system configurations.
     */
    public function update(Request $request)
    {
        $school = app('current_school');

        $validated = $request->validate([
            'date_format'   => 'required|string|max:50',
            'time_format'   => 'required|string|max:50',
            'currency'      => 'required|string|max:20',
            'timezone'      => 'required|string|max:100',
            'language'      => 'required|string|max:10',
            'page_length'   => 'required|integer|min:5|max:100',
            'footer_credit' => 'nullable|string|max:255',
        ]);

        // Merge into JSON settings
        $currentSettings = $school->settings ?? [];
        $newSettings = array_merge($currentSettings, [
            'date_format'   => $validated['date_format'],
            'time_format'   => $validated['time_format'],
            'page_length'   => $validated['page_length'],
            'footer_credit' => $validated['footer_credit'],
        ]);

        $school->update([
            'currency' => $validated['currency'],
            'timezone' => $validated['timezone'],
            'language' => $validated['language'],
            'settings' => $newSettings,
        ]);

        return redirect()->back()->with('success', 'System configurations saved successfully.');
    }
}
