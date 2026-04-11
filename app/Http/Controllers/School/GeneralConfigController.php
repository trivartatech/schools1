<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GeneralConfigController extends Controller
{
    /**
     * Display the General Configuration page.
     */
    public function index()
    {
        $school = app('current_school');
        $settings = $school->settings ?? [];

        return Inertia::render('School/Settings/GeneralConfig', [
            'school'   => $school,
            'settings' => $settings,
        ]);
    }

    /**
     * Update general configuration fields.
     * Core identity fields go on the school model directly;
     * extended fields (meta, address breakdown, fax, financial year) are stored in settings JSON.
     */
    public function update(Request $request)
    {
        $school = app('current_school');

        $validated = $request->validate([
            // School identity (was SchoolProfileController)
            'name'               => 'required|string|max:255',
            'code'               => ['nullable', 'string', 'max:50', \Illuminate\Validation\Rule::unique('schools')->ignore($school->id)],
            'board'              => 'required|in:CBSE,ICSE,State',
            'affiliation_no'     => 'nullable|string|max:100',
            'udise_code'         => 'nullable|string|max:100',
            'principal_name'     => 'nullable|string|max:255',

            // App identity
            'app_name'           => 'nullable|string|max:255',
            'app_description'    => 'nullable|string|max:500',

            // Meta
            'meta_author'        => 'nullable|string|max:255',
            'meta_description'   => 'nullable|string|max:500',
            'meta_keywords'      => 'nullable|string|max:500',

            // Address
            'address_line1'      => 'nullable|string|max:255',
            'address_line2'      => 'nullable|string|max:255',
            'city'               => 'nullable|string|max:100',
            'state'              => 'nullable|string|max:100',
            'zipcode'            => 'nullable|string|max:20',
            'country'            => 'nullable|string|max:100',

            // Contact
            'email'              => 'nullable|email|max:255',
            'phone'              => 'nullable|string|max:50',
            'fax'                => 'nullable|string|max:50',
            'website'            => 'nullable|url|max:255',

            // Financial Year
            'financial_year_code' => 'nullable|string|max:50',

            // GPS
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // Direct school model columns
        $directFields = [
            'name'           => $validated['name'],
            'code'           => $validated['code']           ?? null,
            'board'          => $validated['board'],
            'affiliation_no' => $validated['affiliation_no'] ?? null,
            'udise_code'     => $validated['udise_code']     ?? null,
            'principal_name' => $validated['principal_name'] ?? null,
            'city'           => $validated['city']           ?? null,
            'state'          => $validated['state']          ?? null,
            'pincode'        => $validated['zipcode']        ?? null,
            'email'          => $validated['email']          ?? null,
            'phone'          => $validated['phone']          ?? null,
            'website'        => $validated['website']        ?? null,
        ];

        // Extended fields stored in settings JSON
        $currentSettings = $school->settings ?? [];
        $newSettings = array_merge($currentSettings, [
            'app_name'            => $validated['app_name']            ?? null,
            'app_description'     => $validated['app_description']     ?? null,
            'meta_author'         => $validated['meta_author']         ?? null,
            'meta_description'    => $validated['meta_description']    ?? null,
            'meta_keywords'       => $validated['meta_keywords']       ?? null,
            'address_line1'       => $validated['address_line1']       ?? null,
            'address_line2'       => $validated['address_line2']       ?? null,
            'zipcode'             => $validated['zipcode']             ?? null,
            'country'             => $validated['country']             ?? null,
            'fax'                 => $validated['fax']                 ?? null,
            'financial_year_code' => $validated['financial_year_code'] ?? null,
            'latitude'            => $validated['latitude']            ?? null,
            'longitude'           => $validated['longitude']           ?? null,
        ]);

        $school->update(array_merge($directFields, ['settings' => $newSettings]));

        return redirect()->back()->with('success', 'General configuration saved successfully.');
    }
}
