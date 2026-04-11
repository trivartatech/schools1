<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AssetConfigController extends Controller
{
    /**
     * Display the Asset Configuration page.
     */
    public function index()
    {
        $school = app('current_school');
        $settings = $school->settings ?? [];

        return Inertia::render('School/Settings/AssetConfig', [
            'school'   => $school,
            'settings' => $settings,
        ]);
    }

    /**
     * Update school assets (logo, icon, favicon).
     */
    public function update(Request $request)
    {
        $school = app('current_school');

        $request->validate([
            'logo'     => 'nullable|image|max:2048',    // Max 2MB
            'icon'     => 'nullable|image|max:2048',    // Max 2MB
            'favicon'  => 'nullable|image|max:1024',    // Max 1MB
        ]);

        $updateData = [];
        $currentSettings = $school->settings ?? [];
        $settingsUpdated = false;

        // Handle Logo (600x200)
        if ($request->hasFile('logo')) {
            if ($school->logo) {
                Storage::disk('public')->delete($school->logo);
            }
            $updateData['logo'] = $request->file('logo')->store('schools/logos', 'public');
        }

        // Handle Favicon (128x128)
        if ($request->hasFile('favicon')) {
            if ($school->favicon) {
                Storage::disk('public')->delete($school->favicon);
            }
            $updateData['favicon'] = $request->file('favicon')->store('schools/favicons', 'public');
        }

        // Handle Icon (512x512) - Store in settings
        if ($request->hasFile('icon')) {
            if (isset($currentSettings['icon'])) {
                Storage::disk('public')->delete($currentSettings['icon']);
            }
            $currentSettings['icon'] = $request->file('icon')->store('schools/icons', 'public');
            $settingsUpdated = true;
        }

        if ($settingsUpdated) {
            $updateData['settings'] = $currentSettings;
        }

        if (!empty($updateData)) {
            $school->update($updateData);
        }

        return redirect()->back()->with('success', 'Assets updated successfully.');
    }

    /**
     * Remove an asset.
     */
    public function destroy($type)
    {
        if (!in_array($type, ['logo', 'icon', 'favicon'])) {
            abort(404);
        }

        $school = app('current_school');
        $currentSettings = $school->settings ?? [];

        if ($type === 'logo') {
            if ($school->logo) {
                Storage::disk('public')->delete($school->logo);
                $school->update(['logo' => null]);
            }
        } elseif ($type === 'favicon') {
            if ($school->favicon) {
                Storage::disk('public')->delete($school->favicon);
                $school->update(['favicon' => null]);
            }
        } elseif ($type === 'icon') {
            if (isset($currentSettings['icon'])) {
                Storage::disk('public')->delete($currentSettings['icon']);
                unset($currentSettings['icon']);
                $school->update(['settings' => $currentSettings]);
            }
        }

        return redirect()->back()->with('success', ucfirst($type) . ' removed successfully.');
    }
}
