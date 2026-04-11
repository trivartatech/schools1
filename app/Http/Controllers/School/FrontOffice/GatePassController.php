<?php

namespace App\Http\Controllers\School\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\GatePass;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class GatePassController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');
        $yearId = session('selected_academic_year_id');

        $query = GatePass::where('school_id', $schoolId)
                    ->with(['user', 'requestedBy', 'verifiedBy']);

        if ($yearId) {
            $query->where('academic_year_id', $yearId);
        }

        return Inertia::render('School/FrontOffice/GatePasses/Index', [
            'gatePasses' => $query->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pass_type' => 'required|in:Student,Visitor,Staff',
            'user_type' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'requested_by_type' => 'nullable|string',
            'requested_by_id' => 'nullable|integer',
            'verification_method' => 'nullable|string',
            'picked_up_by_name' => 'nullable|string',
            'relationship' => 'nullable|string',
            'reason' => 'nullable|string',
            'photo_base64' => 'nullable|string',
        ]);

        $pass = new GatePass($validated);
        $pass->school_id = app('current_school_id');
        $pass->academic_year_id = session('selected_academic_year_id');
        $pass->status = 'Pending';
        $pass->qr_code_token = Str::random(32);

        if ($request->filled('photo_base64')) {
            $pass->picker_photo_path = $this->saveBase64Image($request->photo_base64, 'front-office/gate-passes');
        }

        $pass->save();

        return back()->with('success', 'Gate Pass requested successfully.');
    }

    public function updateStatus(Request $request, GatePass $gatePass)
    {
        abort_if($gatePass->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'status' => 'required|in:Pending,Approved,Rejected,Exited,Returned',
            'approval_notes' => 'nullable|string',
        ]);

        // Validate status transitions
        $allowed = match ($gatePass->status) {
            'Pending'  => ['Approved', 'Rejected'],
            'Approved' => ['Exited', 'Rejected'],
            'Exited'   => ['Returned'],
            default    => [],
        };

        if (!in_array($validated['status'], $allowed)) {
            return back()->withErrors(['status' => "Cannot change status from {$gatePass->status} to {$validated['status']}."]);
        }

        $gatePass->status = $validated['status'];

        if ($request->has('approval_notes')) {
            $gatePass->approval_notes = $validated['approval_notes'];
        }

        if ($validated['status'] === 'Exited') {
            $gatePass->exit_time = now();
        } elseif ($validated['status'] === 'Returned') {
            $gatePass->return_time = now();
        }

        if ($validated['status'] === 'Approved') {
            $gatePass->verified_by = auth()->id();
        }

        $gatePass->save();

        return back()->with('success', "Gate Pass status updated to {$validated['status']}.");
    }

    public function destroy(GatePass $gatePass)
    {
        abort_if($gatePass->school_id !== app('current_school_id'), 403);

        $gatePass->delete();
        return back()->with('success', 'Gate Pass deleted.');
    }

    public function uploadPhoto(Request $request, GatePass $gatePass)
    {
        abort_if($gatePass->school_id !== app('current_school_id'), 403);

        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        if ($request->hasFile('photo')) {
            if ($gatePass->picker_photo_path) {
                Storage::disk('public')->delete($gatePass->picker_photo_path);
            }
            $path = $request->file('photo')->store('front-office/gate-passes', 'public');
            $gatePass->update(['picker_photo_path' => $path]);
        }

        return back()->with('success', 'Photo uploaded successfully.');
    }

    /**
     * QR Scanner page.
     */
    public function scanner()
    {
        return Inertia::render('School/FrontOffice/GatePasses/Scanner');
    }

    /**
     * Verify a gate pass by QR token.
     */
    public function verifyQR(Request $request)
    {
        $request->validate(['token' => 'required|string|max:64']);

        $schoolId = app('current_school_id');
        $pass = GatePass::where('school_id', $schoolId)
            ->where('qr_code_token', $request->token)
            ->with(['user', 'requestedBy', 'verifiedBy'])
            ->first();

        if (!$pass) {
            return response()->json(['valid' => false, 'message' => 'Gate pass not found.'], 404);
        }

        return response()->json([
            'valid'   => true,
            'pass'    => $pass,
            'can_exit' => in_array($pass->status, ['Approved']),
        ]);
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

            $imageInfo = @getimagesizefromstring($decoded);
            if ($imageInfo === false) return null;

            $imageName = $dir . '/' . uniqid('gp_', true) . '.jpg';
            Storage::disk('public')->put($imageName, $decoded);
            return $imageName;
        }
        return null;
    }
}
