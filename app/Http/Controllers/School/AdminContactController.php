<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\AdminContact;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminContactController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');

        $contacts = AdminContact::where('school_id', $schoolId)
            ->orderBy('id')
            ->get(['id', 'name', 'phone', 'whatsapp_number']);

        return Inertia::render('School/Settings/AdminContacts', [
            'contacts' => $contacts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'phone'           => 'required|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
        ]);

        AdminContact::create([
            'school_id'       => app('current_school_id'),
            'name'            => $validated['name'],
            'phone'           => $validated['phone'],
            'whatsapp_number' => $validated['whatsapp_number'] ?? null,
        ]);

        return back()->with('success', 'Admin contact added successfully.');
    }

    public function update(Request $request, AdminContact $contact)
    {
        abort_if($contact->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'phone'           => 'required|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
        ]);

        $contact->update($validated);

        return back()->with('success', 'Admin contact updated successfully.');
    }

    public function destroy(AdminContact $contact)
    {
        abort_if($contact->school_id !== app('current_school_id'), 403);

        $contact->delete();

        return back()->with('success', 'Admin contact deleted successfully.');
    }
}
