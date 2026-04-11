<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FeePayment;
use App\Models\FeeHead;
use App\Models\OnlinePaymentOrder;
use App\Models\Student;
use App\Models\StudentParent;
use App\Services\FeeService;
use App\Services\GlPostingService;
use App\Services\RazorpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PortalFeeController extends Controller
{
    public function __construct(
        protected FeeService $feeService,
        protected RazorpayService $razorpay,
    ) {}

    /**
     * Get the students linked to the authenticated user (parent or student).
     */
    protected function getStudents(Request $request): \Illuminate\Support\Collection
    {
        $user = $request->user();

        if ($user->isStudent()) {
            $student = Student::where('user_id', $user->id)
                ->with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
                ->first();

            return $student ? collect([$student]) : collect();
        }

        if ($user->isParent()) {
            $parent = StudentParent::where('user_id', $user->id)->first();
            if (!$parent) return collect();

            return Student::where('parent_id', $parent->id)
                ->with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
                ->get();
        }

        return collect();
    }

    /**
     * Fee summary page — shows all pending fees and payment history.
     */
    public function index(Request $request)
    {
        $user           = $request->user();
        $students       = $this->getStudents($request);
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $studentsData = $students->map(function ($student) use ($academicYearId) {
            $summary  = $academicYearId
                ? $this->feeService->getStudentFeeSummary($student, $academicYearId)
                : ['total_due' => 0, 'paid' => 0, 'discount' => 0, 'fine' => 0, 'balance' => 0, 'fee_heads' => []];

            return [
                'id'            => $student->id,
                'name'          => $student->first_name . ' ' . $student->last_name,
                'admission_no'  => $student->admission_no,
                'class_name'    => $student->currentAcademicHistory?->courseClass?->name ?? 'N/A',
                'section_name'  => $student->currentAcademicHistory?->section?->name ?? 'N/A',
                'fee_summary'   => $summary,
            ];
        });

        // Check if online payment is enabled for this school
        $school = app()->bound('current_school') ? app('current_school') : null;
        $paymentEnabled = $school && !empty(config('payment.razorpay.key_id'));

        return Inertia::render('Portal/Fees/Index', [
            'students'        => $studentsData,
            'payment_enabled' => $paymentEnabled,
            'razorpay_key'    => $paymentEnabled ? config('payment.razorpay.key_id') : null,
            'school_name'     => $school?->name ?? 'School',
            'school_logo'     => $school?->logo ?? null,
        ]);
    }

    /**
     * Create a Razorpay order for selected fee items.
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer',
            'fee_items'  => 'required|array|min:1',
            'fee_items.*.fee_head_id' => 'required|integer',
            'fee_items.*.term'        => 'required|string',
            'fee_items.*.amount'      => 'required|numeric|min:1',
        ]);

        $user     = $request->user();
        $students = $this->getStudents($request);
        $student  = $students->firstWhere('id', $request->student_id);

        if (!$student) {
            return back()->withErrors(['student_id' => 'Unauthorized: student not linked to your account.']);
        }

        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        if (!$academicYearId) {
            return back()->withErrors(['error' => 'No active academic year.']);
        }

        // Calculate total amount from submitted fee items
        $totalAmount = collect($request->fee_items)->sum('amount');
        $amountPaise = (int) round($totalAmount * 100);

        if ($amountPaise < 100) {
            return back()->withErrors(['error' => 'Minimum payment amount is ₹1.']);
        }

        // Create Razorpay order
        $receipt = 'OP-' . $student->id . '-' . time();
        $rzpOrder = $this->razorpay->createOrder($amountPaise, 'INR', $receipt, [
            'student_id'   => $student->id,
            'student_name' => $student->first_name . ' ' . $student->last_name,
        ]);

        if (!$rzpOrder) {
            return back()->withErrors(['error' => 'Unable to create payment order. Please try again.']);
        }

        // Save order record
        $order = OnlinePaymentOrder::create([
            'school_id'        => $student->school_id,
            'student_id'       => $student->id,
            'academic_year_id' => $academicYearId,
            'initiated_by'     => $user->id,
            'gateway'          => 'razorpay',
            'gateway_order_id' => $rzpOrder['id'],
            'amount_paise'     => $amountPaise,
            'currency'         => 'INR',
            'fee_items'        => $request->fee_items,
            'status'           => 'created',
        ]);

        return response()->json([
            'order_id'     => $rzpOrder['id'],
            'amount_paise' => $amountPaise,
            'currency'     => 'INR',
            'key'          => config('payment.razorpay.key_id'),
            'name'         => app()->bound('current_school') ? app('current_school')->name : 'School',
            'description'  => 'Fee Payment for ' . $student->first_name,
            'prefill'      => [
                'name'  => $user->name,
                'email' => $user->email ?? '',
                'contact' => $user->phone ?? '',
            ],
        ]);
    }

    /**
     * Verify payment after Razorpay checkout completes.
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
        ]);

        $order = OnlinePaymentOrder::where('gateway_order_id', $request->razorpay_order_id)->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($order->status !== 'created') {
            return response()->json(['success' => false, 'message' => 'Order already processed.'], 409);
        }

        // Verify signature
        $valid = $this->razorpay->verifySignature(
            $request->razorpay_order_id,
            $request->razorpay_payment_id,
            $request->razorpay_signature,
        );

        if (!$valid) {
            $order->update([
                'status'         => 'failed',
                'failure_reason' => 'Signature verification failed',
            ]);

            return response()->json(['success' => false, 'message' => 'Payment verification failed.'], 400);
        }

        // Mark order as paid
        $order->update([
            'gateway_payment_id' => $request->razorpay_payment_id,
            'gateway_signature'  => $request->razorpay_signature,
            'status'             => 'paid',
            'paid_at'            => now(),
        ]);

        // Process: create FeePayment records for each fee item
        $this->processOrder($order);

        return response()->json([
            'success' => true,
            'message' => 'Payment successful! Fee receipts have been generated.',
        ]);
    }

    /**
     * Convert a paid OnlinePaymentOrder into FeePayment records.
     */
    protected function processOrder(OnlinePaymentOrder $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->fee_items as $item) {
                $feeHeadId = $item['fee_head_id'];
                $term      = $item['term'];
                $amount    = (float) $item['amount'];

                // Check for tax on fee head
                $feeHead       = FeeHead::find($feeHeadId);
                $taxableAmount = $amount;
                $taxAmount     = 0.00;
                $taxPercent    = 0.00;

                if ($feeHead && $feeHead->is_taxable && $feeHead->gst_percent > 0) {
                    $taxPercent    = (float) $feeHead->gst_percent;
                    $taxableAmount = round($amount / (1 + ($taxPercent / 100)), 2);
                    $taxAmount     = round($amount - $taxableAmount, 2);
                }

                // Check if a 'due' FeePayment already exists for this head+term
                $existing = FeePayment::where('student_id', $order->student_id)
                    ->where('academic_year_id', $order->academic_year_id)
                    ->where('fee_head_id', $feeHeadId)
                    ->where('term', $term)
                    ->where('status', 'due')
                    ->where('amount_paid', 0)
                    ->first();

                if ($existing) {
                    $balance = max(0, $existing->amount_due - $amount);
                    $status  = $balance <= 0 ? 'paid' : 'partial';

                    $existing->update([
                        'amount_paid'     => $amount,
                        'balance'         => $balance,
                        'status'          => $status,
                        'payment_mode'    => 'online',
                        'payment_date'    => now()->toDateString(),
                        'transaction_ref' => $order->gateway_payment_id,
                        'taxable_amount'  => $taxableAmount,
                        'tax_amount'      => $taxAmount,
                        'tax_percent'     => $taxPercent,
                        'remarks'         => 'Online payment via Razorpay',
                        'collected_by'    => $order->initiated_by,
                    ]);

                    if (!$existing->gl_transaction_id && $amount > 0) {
                        app(GlPostingService::class)->postFeePayment($existing->fresh());
                    }
                } else {
                    $amountDue = $amount;

                    // Try to find the structure amount for proper due tracking
                    $structure = \App\Models\FeeStructure::where('school_id', $order->school_id)
                        ->where('academic_year_id', $order->academic_year_id)
                        ->where('fee_head_id', $feeHeadId)
                        ->where('term', $term)
                        ->first();

                    if ($structure) {
                        $amountDue = (float) $structure->amount;
                    }

                    $balance = max(0, $amountDue - $amount);
                    $status  = $balance <= 0 ? 'paid' : 'partial';

                    FeePayment::create([
                        'school_id'        => $order->school_id,
                        'student_id'       => $order->student_id,
                        'academic_year_id' => $order->academic_year_id,
                        'fee_head_id'      => $feeHeadId,
                        'term'             => $term,
                        'amount_due'       => $amountDue,
                        'amount_paid'      => $amount,
                        'discount'         => 0,
                        'fine'             => 0,
                        'balance'          => $balance,
                        'payment_mode'     => 'online',
                        'payment_date'     => now()->toDateString(),
                        'transaction_ref'  => $order->gateway_payment_id,
                        'status'           => $status,
                        'remarks'          => 'Online payment via Razorpay',
                        'collected_by'     => $order->initiated_by,
                        'taxable_amount'   => $taxableAmount,
                        'tax_amount'       => $taxAmount,
                        'tax_percent'      => $taxPercent,
                    ]);
                }
            }

            $order->update([
                'status'       => 'processed',
                'processed_at' => now(),
            ]);
        });
    }

    /**
     * Payment history for the portal user's students.
     */
    public function history(Request $request)
    {
        $students       = $this->getStudents($request);
        $studentIds     = $students->pluck('id')->all();
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $payments = FeePayment::whereIn('student_id', $studentIds)
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->where('amount_paid', '>', 0)
            ->with(['student:id,first_name,last_name,admission_no', 'feeHead:id,name'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(20);

        // Online payment orders
        $orders = OnlinePaymentOrder::whereIn('student_id', $studentIds)
            ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
            ->with('student:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'orders_page');

        $studentsMap = $students->map(fn($s) => [
            'id'   => $s->id,
            'name' => $s->first_name . ' ' . $s->last_name,
        ]);

        return Inertia::render('Portal/Fees/History', [
            'payments' => $payments,
            'orders'   => $orders,
            'students' => $studentsMap,
        ]);
    }
}
