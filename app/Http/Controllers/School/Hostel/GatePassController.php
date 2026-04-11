<?php

namespace App\Http\Controllers\School\Hostel;

use App\Http\Controllers\Controller;
use App\Models\HostelLeaveRequest;
use App\Models\HostelStudent;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GatePassController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $query = HostelLeaveRequest::where('school_id', $schoolId)
                    ->with(['student', 'approver']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $gatePasses = $query->latest()->paginate(20);

        $activeHostelStudentIds = HostelStudent::where('school_id', $schoolId)
                    ->where('status', 'Active')
                    ->pluck('student_id');

        $students = Student::whereIn('id', $activeHostelStudentIds)
                           ->get(['id', 'first_name', 'last_name', 'admission_no']);

        return Inertia::render('School/Hostel/GatePasses/Index', [
            'gatePasses' => $gatePasses,
            'students' => $students,
            'filters' => $request->only('status')
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        // Ensure student is actively in hostel
        $activeStudentIds = HostelStudent::where('school_id', $schoolId)
            ->where('status', 'Active')
            ->pluck('student_id')
            ->toArray();

        $validated = $request->validate([
            'student_id'         => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId), Rule::in($activeStudentIds)],
            'leave_type'         => 'required|in:Day Out,Night Out,Home Time,Emergency',
            'from_date'          => 'required|date',
            'to_date'            => 'required|date|after_or_equal:from_date',
            'reason'             => 'required|string',
            'destination'        => 'nullable|string|max:300',
            'escort_name'        => 'nullable|string|max:255',
            'escort_relation'    => 'nullable|string|max:100',
            'escort_phone'       => 'nullable|string|max:20',
            'escort_id_proof_type' => 'nullable|string|max:50',
            'parent_name'        => 'nullable|string|max:255',
        ]);

        $validated['school_id'] = $schoolId;
        $validated['status'] = 'Pending';
        $validated['parent_approval'] = 'Pending';

        HostelLeaveRequest::create($validated);

        return back()->with('success', 'Gate pass request created');
    }

    public function updateStatus(Request $request, HostelLeaveRequest $gatePass)
    {
        abort_if($gatePass->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'status'          => 'required|in:Approved,Rejected,Out,Returned',
            'actual_out_time' => 'nullable',
            'actual_in_time'  => 'nullable',
            'late_reason'     => 'nullable|string',
        ]);

        // Validate status transitions
        $allowed = match ($gatePass->status) {
            'Pending'  => ['Approved', 'Rejected'],
            'Approved' => ['Out', 'Rejected'],
            'Out'      => ['Returned'],
            default    => [],
        };

        if (!in_array($validated['status'], $allowed)) {
            return back()->with('error', "Cannot change status from {$gatePass->status} to {$validated['status']}.");
        }

        if (in_array($validated['status'], ['Approved', 'Rejected'])) {
            $validated['approved_by'] = auth()->id();
        }

        if ($validated['status'] === 'Returned') {
            $validated['is_expired'] = true;
        }

        $gatePass->update($validated);
        return back()->with('success', 'Gate pass status updated');
    }

    public function verifyParentOtp(Request $request, HostelLeaveRequest $gatePass)
    {
        abort_if($gatePass->school_id !== app('current_school_id'), 403);

        $request->validate(['otp' => 'required|string']);

        if ($gatePass->parent_otp && \Illuminate\Support\Facades\Hash::check($request->otp, $gatePass->parent_otp)) {
            $gatePass->update([
                'parent_otp' => null,
                'parent_otp_verified' => true,
                'parent_approval' => 'Approved',
                'parent_approved_at' => now(),
            ]);
            return back()->with('success', 'Parent OTP verified');
        }

        return back()->with('error', 'Invalid OTP');
    }

    public function sendParentOtp(Request $request, HostelLeaveRequest $gatePass)
    {
        abort_if($gatePass->school_id !== app('current_school_id'), 403);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $gatePass->update([
            'parent_otp'          => \Illuminate\Support\Facades\Hash::make($otp),
            'parent_otp_verified' => false,
        ]);

        $school = app('current_school');
        $phone  = $gatePass->escort_phone ?? null;

        if ($school && $phone) {
            try {
                $notificationService = new \App\Services\NotificationService($school);
                $notificationService->sendSms(
                    $phone,
                    "Your OTP for hostel gate pass approval is: {$otp}. Valid for 10 minutes. Do not share."
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Hostel OTP SMS failed', [
                    'gate_pass_id' => $gatePass->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', "OTP sent to parent/guardian. [DEV] OTP is: {$otp}");
    }

    public function uploadPhoto(Request $request, HostelLeaveRequest $gatePass)
    {
        abort_if($gatePass->school_id !== app('current_school_id'), 403);

        $request->validate([
            'photo_type' => 'required|in:student_exit_photo,escort_exit_photo,student_return_photo',
            'photo_data' => 'required|string',
        ]);

        $base64Data = $request->photo_data;
        if (str_contains($base64Data, ',')) {
            $base64Data = explode(',', $base64Data)[1];
        }

        $decoded = base64_decode($base64Data);

        if (!$decoded || !getimagesizefromstring($decoded)) {
            return back()->with('error', 'Invalid image data provided.');
        }

        $filename = 'hostel/gate-pass/' . $gatePass->id . '/' . $request->photo_type . '_' . time() . '.jpg';
        Storage::disk('public')->put($filename, $decoded);

        $gatePass->update([
            $request->photo_type => $filename
        ]);

        return back()->with('success', 'Photo saved');
    }

    public function destroy(HostelLeaveRequest $gatePass)
    {
        abort_if($gatePass->school_id !== app('current_school_id'), 403);

        $gatePass->delete();
        return back()->with('success', 'Gate pass deleted');
    }
}
