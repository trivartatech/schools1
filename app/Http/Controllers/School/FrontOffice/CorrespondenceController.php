<?php

namespace App\Http\Controllers\School\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class CorrespondenceController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');

        $query = Correspondence::where('school_id', $schoolId)
                    ->with('department');

        if ($yearId = session('selected_academic_year_id')) {
            $query->where('academic_year_id', $yearId);
        }

        return Inertia::render('School/FrontOffice/Correspondence/Index', [
            'correspondences' => $query->latest('date')->get(),
            'departments' => Department::where('school_id', $schoolId)->get()
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'type' => 'required|in:Incoming,Outgoing',
            'reference_number' => 'nullable|string|max:100',
            'sender_receiver_name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('school_id', $schoolId)],
            'date' => 'required|date',
            'dispatch_tracking' => 'nullable|string|max:100',
            'courier_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'file' => 'nullable|file|max:10240',
        ]);

        $entry = new Correspondence($validated);
        $entry->school_id = $schoolId;
        $entry->academic_year_id = session('selected_academic_year_id');

        if ($request->hasFile('file')) {
            $entry->attachment_path = $request->file('file')->store('front-office/correspondence', 'public');
        }

        $entry->save();

        return back()->with('success', 'Correspondence record saved.');
    }

    /**
     * Update delivery status.
     */
    public function updateStatus(Request $request, Correspondence $correspondence)
    {
        abort_if($correspondence->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'delivery_status' => 'required|in:pending,in_transit,delivered,returned',
        ]);

        $correspondence->update($validated);

        return back()->with('success', 'Delivery status updated.');
    }

    /**
     * Mark correspondence as acknowledged.
     */
    public function acknowledge(Request $request, Correspondence $correspondence)
    {
        abort_if($correspondence->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'acknowledged_by' => 'nullable|string|max:255',
        ]);

        $correspondence->update([
            'acknowledged' => true,
            'acknowledged_at' => now(),
            'acknowledged_by' => $validated['acknowledged_by'] ?? auth()->user()->name,
        ]);

        return back()->with('success', 'Correspondence acknowledged.');
    }

    public function destroy(Correspondence $correspondence)
    {
        abort_if($correspondence->school_id !== app('current_school_id'), 403);

        if ($correspondence->attachment_path) {
            Storage::disk('public')->delete($correspondence->attachment_path);
        }
        $correspondence->delete();

        return back()->with('success', 'Correspondence record deleted.');
    }
}
