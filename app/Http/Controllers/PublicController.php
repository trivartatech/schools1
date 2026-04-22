<?php

namespace App\Http\Controllers;

use App\Models\CertificateIssuance;
use App\Models\FeePayment;
use App\Models\HostelLeaveRequest;
use App\Models\HostelVisitor;
use App\Models\TransportFeePayment;
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

    public function verifyTransportReceipt(string $receipt_no)
    {
        $payment = TransportFeePayment::where('receipt_no', $receipt_no)
            ->with(['student', 'school', 'allocation.route'])
            ->first();

        if (!$payment) {
            return response('Invalid or unrecognized receipt number.', 404);
        }

        return view('verify-transport-receipt', compact('payment'));
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

    public function verifyCertificate(string $token)
    {
        $issuance = CertificateIssuance::where('verification_token', $token)
            ->with(['student', 'template', 'school', 'issuedBy'])
            ->first();

        if (!$issuance) {
            return Inertia::render('Public/CertificateVerify', [
                'issuance' => null,
                'error'    => 'This certificate could not be verified. The token may be invalid or expired.',
            ]);
        }

        return Inertia::render('Public/CertificateVerify', [
            'issuance' => [
                'id'               => $issuance->id,
                'certificate_no'   => $issuance->certificate_no,
                'issued_date'      => $issuance->issued_date?->format('d F Y'),
                'template_name'    => $issuance->template->name,
                'school_name'      => $issuance->school->name,
                'student_name'     => $issuance->student->name,
                'admission_no'     => $issuance->student->admission_no,
                'issued_by'        => $issuance->issuedBy?->name,
                'verification_token' => $issuance->verification_token,
                'created_at'       => $issuance->created_at->format('d M Y'),
            ],
            'error'    => null,
        ]);
    }
}
