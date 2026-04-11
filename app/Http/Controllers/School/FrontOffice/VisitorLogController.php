<?php

namespace App\Http\Controllers\School\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\VisitorLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class VisitorLogController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');
        $yearId = session('selected_academic_year_id');

        $query = VisitorLog::where('school_id', $schoolId)
                    ->with('personToMeet');

        if ($yearId) {
            $query->where('academic_year_id', $yearId);
        }

        $visitors = $query->latest()->get();

        // Expected visitors for today (pre-registered, not yet checked in)
        $today = now()->toDateString();
        $expected = VisitorLog::where('school_id', $schoolId)
            ->where('is_pre_registered', true)
            ->where('expected_date', $today)
            ->whereNull('in_time')
            ->get();

        return Inertia::render('School/FrontOffice/VisitorLogs/Index', [
            'visitors' => $visitors,
            'expectedVisitors' => $expected,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'purpose' => 'required|in:Meeting,Admission,Delivery,Other',
            'person_to_meet_type' => 'nullable|string',
            'person_to_meet_id' => 'nullable|integer',
            'in_time' => 'nullable|date',
            'notes' => 'nullable|string',
            'photo_base64' => 'nullable|string',
        ]);

        $visitor = new VisitorLog($validated);
        $visitor->school_id = $schoolId;
        $visitor->academic_year_id = session('selected_academic_year_id');

        if (empty($visitor->in_time)) {
            $visitor->in_time = now();
        }

        if ($request->filled('photo_base64')) {
            $visitor->photo_path = $this->saveBase64Image($request->photo_base64, 'front-office/visitors');
        }

        $visitor->save();

        return back()->with('success', 'Visitor logged successfully.');
    }

    public function update(Request $request, VisitorLog $visitor)
    {
        abort_if($visitor->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'purpose' => 'required|in:Meeting,Admission,Delivery,Other',
            'person_to_meet_type' => 'nullable|string',
            'person_to_meet_id' => 'nullable|integer',
            'out_time' => 'nullable|date',
            'notes' => 'nullable|string',
            'mark_exit' => 'nullable|boolean',
        ]);

        // If mark_exit requested, set out_time to now
        if (!empty($validated['mark_exit'])) {
            $validated['out_time'] = now();
        }
        unset($validated['mark_exit']);

        $visitor->update($validated);

        return back()->with('success', 'Visitor log updated successfully.');
    }

    /**
     * Pre-register an expected visitor.
     */
    public function preRegister(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'purpose' => 'required|in:Meeting,Admission,Delivery,Other',
            'expected_date' => 'required|date|after_or_equal:today',
            'expected_time' => 'nullable|string|max:10',
            'person_to_meet_type' => 'nullable|string',
            'person_to_meet_id' => 'nullable|integer',
            'notes' => 'nullable|string',
            'id_type' => 'nullable|string|max:50',
            'id_number' => 'nullable|string|max:100',
        ]);

        $visitor = new VisitorLog($validated);
        $visitor->school_id = $schoolId;
        $visitor->academic_year_id = session('selected_academic_year_id');
        $visitor->is_pre_registered = true;
        $visitor->pre_registered_by = auth()->id();

        $visitor->save();

        return back()->with('success', 'Visitor pre-registered for ' . $validated['expected_date']);
    }

    /**
     * Check in a pre-registered visitor (sets in_time).
     */
    public function checkIn(VisitorLog $visitor)
    {
        abort_if($visitor->school_id !== app('current_school_id'), 403);

        if ($visitor->in_time) {
            return back()->withErrors(['error' => 'Visitor already checked in.']);
        }

        $visitor->update([
            'in_time' => now(),
            'badge_number' => request('badge_number'),
        ]);

        return back()->with('success', $visitor->name . ' checked in successfully.');
    }

    public function destroy(VisitorLog $visitor)
    {
        abort_if($visitor->school_id !== app('current_school_id'), 403);

        $visitor->delete();
        return back()->with('success', 'Visitor log removed.');
    }

    public function uploadPhoto(Request $request, VisitorLog $visitor)
    {
        abort_if($visitor->school_id !== app('current_school_id'), 403);

        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        if ($request->hasFile('photo')) {
            if ($visitor->photo_path) {
                Storage::disk('public')->delete($visitor->photo_path);
            }
            $path = $request->file('photo')->store('front-office/visitors', 'public');
            $visitor->update(['photo_path' => $path]);
        }

        return back()->with('success', 'Photo uploaded successfully.');
    }

    private function saveBase64Image($base64Image, $dir)
    {
        $parts = explode(';', $base64Image, 2);
        if (count($parts) < 2) return null;

        $dataParts = explode(',', $parts[1], 2);
        $file_data = $dataParts[1] ?? null;

        if ($file_data) {
            $decoded = base64_decode($file_data, true);
            if ($decoded === false) return null;

            // Validate it's actually an image
            $imageInfo = @getimagesizefromstring($decoded);
            if ($imageInfo === false) return null;

            $imageName = $dir . '/' . uniqid('v_', true) . '.jpg';
            Storage::disk('public')->put($imageName, $decoded);
            return $imageName;
        }
        return null;
    }
}
