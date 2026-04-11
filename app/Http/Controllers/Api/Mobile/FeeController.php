<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\FeePayment;
use App\Models\Student;
use App\Services\FeeService;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeController extends Controller
{
    public function __construct(protected FeeService $feeService) {}

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve which student's data to serve.
     * For parents with multiple children, honour the X-Active-Student-Id header
     * or `student_id` query param. Always validates ownership.
     */
    private function resolveStudentId($user, ?Request $request = null): ?int
    {
        if ($user->isStudent()) {
            return $user->student?->id;
        }

        if ($user->isParent()) {
            $parent   = $user->studentParent;
            if (!$parent) return null;

            $children = $parent->students()->pluck('id');
            if ($children->isEmpty()) return null;

            // Check for explicit child selection
            $requested = $request?->header('X-Active-Student-Id')
                      ?? $request?->input('student_id');

            if ($requested && $children->contains((int)$requested)) {
                return (int)$requested;
            }

            // Default: first child
            return $children->first();
        }

        return null;
    }

    // ── Fees ──────────────────────────────────────────────────────────────────

    public function fees(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId || !$yearId) {
            return response()->json([
                'total_due' => 0, 'paid' => 0, 'balance' => 0,
                'discount' => 0, 'fine' => 0, 'payments' => [], 'fee_heads' => [],
            ]);
        }

        $student = Student::find($studentId);
        $summary = $student ? $this->feeService->getStudentFeeSummary($student, $yearId, $school->id) : [];

        $payments = FeePayment::where('student_id', $studentId)
            ->where('academic_year_id', $yearId)
            ->with(['feeHead.feeGroup', 'collectedBy:id,name'])
            ->orderByDesc('payment_date')
            ->get();

        return response()->json([
            'total_due'  => $summary['total_due']  ?? 0,
            'paid'       => $summary['paid']        ?? 0,
            'balance'    => $summary['balance']     ?? 0,
            'discount'   => $summary['discount']    ?? 0,
            'fine'       => $summary['fine']        ?? 0,
            'fee_heads'  => $summary['fee_heads']   ?? [],
            'payments'   => $payments,
            'student_id' => $studentId,
        ]);
    }

    public function feeDetail(Request $request, int $id): JsonResponse
    {
        $school  = app('current_school');
        $payment = FeePayment::where('school_id', $school->id)->where('id', $id)
            ->with(['feeHead.feeGroup', 'student', 'collectedBy:id,name', 'academicYear'])
            ->firstOrFail();

        $user      = $request->user();
        $studentId = $this->resolveStudentId($user, $request);
        if ($studentId && $payment->student_id !== $studentId) abort(403);

        return response()->json(['payment' => $payment]);
    }

    // ── Payment History ──────────────────────────────────────────────────────

    public function paymentHistory(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);
        $page      = $request->input('page', 1);

        if (!$studentId) {
            return response()->json(['data' => [], 'current_page' => 1, 'last_page' => 1]);
        }

        $payments = FeePayment::where('school_id', $school->id)
            ->where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->where('status', '!=', 'cancelled')
            ->with(['feeHead:id,name'])
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'page', $page);

        $data = collect($payments->items())->map(fn($p) => [
            'id'              => $p->id,
            'receipt_no'      => $p->receipt_no,
            'fee_head'        => $p->feeHead?->name ?? 'Other',
            'term'            => $p->term,
            'amount'          => (float) $p->amount_paid,
            'amount_paid'     => (float) $p->amount_paid,
            'payment_date'    => $p->payment_date?->toDateString(),
            'payment_mode'    => $p->payment_mode,
            'status'          => strtolower($p->status ?? 'pending'),
            'transaction_id'  => $p->transaction_ref ?: $p->receipt_no,
            'transaction_ref' => $p->transaction_ref,
            'created_at'      => $p->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data'         => $data,
            'current_page' => $payments->currentPage(),
            'last_page'    => $payments->lastPage(),
        ]);
    }

    // ── Payments: Create Order & Verify ────────────────────────────────────

    public function createPaymentOrder(Request $request): JsonResponse
    {
        $request->validate([
            'fee_ids' => 'required|array|min:1',
            'fee_ids.*' => 'integer|exists:fee_payments,id',
        ]);

        $user      = $request->user();
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['message' => 'No student linked to this account.'], 422);
        }

        $fees = FeePayment::where('school_id', $school->id)
            ->where('student_id', $studentId)
            ->whereIn('id', $request->input('fee_ids'))
            ->whereIn('status', ['due', 'partial'])
            ->get();

        if ($fees->isEmpty()) {
            return response()->json(['message' => 'No payable fees found for given IDs.'], 422);
        }

        $totalPaise = (int) round($fees->sum('balance') * 100);

        if ($totalPaise <= 0) {
            return response()->json(['message' => 'Total payable amount must be greater than zero.'], 422);
        }

        $receipt = 'MOBL-' . $school->id . '-' . $studentId . '-' . now()->timestamp;

        $razorpay = app(RazorpayService::class);
        $order = $razorpay->createOrder($totalPaise, 'INR', $receipt, [
            'school_id'  => (string) $school->id,
            'student_id' => (string) $studentId,
            'fee_ids'    => implode(',', $fees->pluck('id')->toArray()),
        ]);

        if (!$order) {
            return response()->json(['message' => 'Failed to create payment order. Please try again.'], 502);
        }

        return response()->json([
            'order_id'   => $order['id'],
            'amount'     => $totalPaise,
            'currency'   => $order['currency'] ?? 'INR',
            'key_id'     => config('payment.razorpay.key_id'),
            'receipt'    => $receipt,
            'fee_ids'    => $fees->pluck('id'),
            'student_id' => $studentId,
        ]);
    }

    public function verifyPayment(Request $request): JsonResponse
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
            'fee_ids'             => 'required|array|min:1',
            'fee_ids.*'           => 'integer',
        ]);

        $razorpay = app(RazorpayService::class);
        $valid = $razorpay->verifySignature(
            $request->input('razorpay_order_id'),
            $request->input('razorpay_payment_id'),
            $request->input('razorpay_signature'),
        );

        if (!$valid) {
            return response()->json(['message' => 'Payment verification failed. Signature mismatch.'], 422);
        }

        $user      = $request->user();
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($user, $request);

        $fees = FeePayment::where('school_id', $school->id)
            ->where('student_id', $studentId)
            ->whereIn('id', $request->input('fee_ids'))
            ->whereIn('status', ['due', 'partial'])
            ->get();

        $now = now();
        foreach ($fees as $fee) {
            $fee->update([
                'amount_paid'     => $fee->amount_due - $fee->discount + $fee->fine,
                'balance'         => 0,
                'status'          => 'paid',
                'payment_mode'    => 'Online',
                'payment_date'    => $now->toDateString(),
                'transaction_ref' => $request->input('razorpay_payment_id'),
                'collected_by'    => $user->id,
                'remarks'         => 'Paid via Razorpay Mobile App. Order: ' . $request->input('razorpay_order_id'),
            ]);
        }

        return response()->json([
            'message'    => 'Payment verified and recorded successfully.',
            'payment_id' => $request->input('razorpay_payment_id'),
            'fees_paid'  => $fees->pluck('id'),
        ]);
    }
}
