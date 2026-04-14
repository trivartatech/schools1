<?php

namespace App\Http\Controllers;

use App\Models\FeePayment;
use App\Models\HostelLeaveRequest;
use App\Models\HostelVisitor;
use Inertia\Inertia;

class PublicController extends Controller
{
    public function home()
    {
        return redirect()->route('login');
    }

    public function verifyReceipt(string $receipt_no)
    {
        $payment = FeePayment::where('receipt_no', $receipt_no)
            ->with(['student', 'feeHead.feeGroup', 'school'])
            ->first();

        if (!$payment) {
            return response('Invalid or unrecognized receipt number.', 404);
        }

        return view('verify-receipt', compact('payment'));
    }

    public function verifyGatePass(string $token)
    {
        $pass = HostelLeaveRequest::where('pass_token', $token)
            ->with(['student', 'approver'])
            ->first();

        if (!$pass) {
            return Inertia::render('School/Hostel/GatePasses/VerifyPublic', [
                'pass' => null, 'error' => 'Invalid or expired gate pass token.',
            ]);
        }

        return Inertia::render('School/Hostel/GatePasses/VerifyPublic', [
            'pass' => $pass, 'error' => null,
        ]);
    }

    public function verifyVisitorPass(string $token)
    {
        $visitor = HostelVisitor::where('pass_token', $token)
            ->with(['student', 'staff.user'])
            ->first();

        if (!$visitor) {
            return Inertia::render('School/Hostel/Visitors/VerifyPublic', [
                'visitor' => null, 'error' => 'Invalid or unrecognized visitor pass.',
            ]);
        }

        return Inertia::render('School/Hostel/Visitors/VerifyPublic', [
            'visitor' => $visitor, 'error' => null,
        ]);
    }
}
