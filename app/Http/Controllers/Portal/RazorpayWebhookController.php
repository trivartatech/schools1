<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\OnlinePaymentOrder;
use App\Services\GlPostingService;
use App\Services\RazorpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    /**
     * Handle incoming Razorpay webhook events.
     * Route: POST /webhooks/razorpay (no auth, no CSRF).
     */
    public function handle(Request $request, RazorpayService $razorpay)
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature', '');

        if (!$razorpay->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Razorpay webhook: invalid signature');
            return response('Invalid signature', 400);
        }

        $data  = json_decode($payload, true);
        $event = $data['event'] ?? '';

        match ($event) {
            'payment.captured' => $this->handlePaymentCaptured($data),
            'payment.failed'   => $this->handlePaymentFailed($data),
            default            => Log::info("Razorpay webhook: unhandled event {$event}"),
        };

        return response('OK', 200);
    }

    protected function handlePaymentCaptured(array $data): void
    {
        $payment = $data['payload']['payment']['entity'] ?? null;
        if (!$payment) return;

        $orderId   = $payment['order_id'] ?? null;
        $paymentId = $payment['id'] ?? null;

        if (!$orderId || !$paymentId) return;

        $order = OnlinePaymentOrder::where('gateway_order_id', $orderId)->first();
        if (!$order) {
            Log::warning("Razorpay webhook: order not found for {$orderId}");
            return;
        }

        // If already processed, skip
        if (in_array($order->status, ['paid', 'processed'])) {
            return;
        }

        $order->update([
            'gateway_payment_id' => $paymentId,
            'status'             => 'paid',
            'paid_at'            => now(),
        ]);

        // Process the order into FeePayment records
        $this->processOrder($order);
    }

    protected function handlePaymentFailed(array $data): void
    {
        $payment = $data['payload']['payment']['entity'] ?? null;
        if (!$payment) return;

        $orderId = $payment['order_id'] ?? null;
        if (!$orderId) return;

        $order = OnlinePaymentOrder::where('gateway_order_id', $orderId)->first();
        if (!$order || $order->status !== 'created') return;

        $reason = $payment['error_description'] ?? $payment['error_reason'] ?? 'Payment failed';

        $order->update([
            'status'         => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    protected function processOrder(OnlinePaymentOrder $order): void
    {
        if ($order->status === 'processed') return;

        DB::transaction(function () use ($order) {
            foreach ($order->fee_items as $item) {
                $feeHeadId = $item['fee_head_id'];
                $term      = $item['term'];
                $amount    = (float) $item['amount'];

                $feeHead       = FeeHead::find($feeHeadId);
                $taxableAmount = $amount;
                $taxAmount     = 0.00;
                $taxPercent    = 0.00;

                if ($feeHead && $feeHead->is_taxable && $feeHead->gst_percent > 0) {
                    $taxPercent    = (float) $feeHead->gst_percent;
                    $taxableAmount = round($amount / (1 + ($taxPercent / 100)), 2);
                    $taxAmount     = round($amount - $taxableAmount, 2);
                }

                $existing = FeePayment::where('student_id', $order->student_id)
                    ->where('academic_year_id', $order->academic_year_id)
                    ->where('fee_head_id', $feeHeadId)
                    ->where('term', $term)
                    ->where('status', 'due')
                    ->where('amount_paid', 0)
                    ->first();

                if ($existing) {
                    $balance = max(0, $existing->amount_due - $amount);
                    $existing->update([
                        'amount_paid'     => $amount,
                        'balance'         => $balance,
                        'status'          => $balance <= 0 ? 'paid' : 'partial',
                        'payment_mode'    => 'online',
                        'payment_date'    => now()->toDateString(),
                        'transaction_ref' => $order->gateway_payment_id,
                        'taxable_amount'  => $taxableAmount,
                        'tax_amount'      => $taxAmount,
                        'tax_percent'     => $taxPercent,
                        'remarks'         => 'Online payment via Razorpay (webhook)',
                        'collected_by'    => $order->initiated_by,
                    ]);

                    if (!$existing->gl_transaction_id && $amount > 0) {
                        app(GlPostingService::class)->postFeePayment($existing->fresh());
                    }
                } else {
                    $amountDue = $amount;
                    $structure = \App\Models\FeeStructure::where('school_id', $order->school_id)
                        ->where('academic_year_id', $order->academic_year_id)
                        ->where('fee_head_id', $feeHeadId)
                        ->where('term', $term)
                        ->first();

                    if ($structure) {
                        $amountDue = (float) $structure->amount;
                    }

                    $balance = max(0, $amountDue - $amount);
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
                        'status'           => $balance <= 0 ? 'paid' : 'partial',
                        'remarks'          => 'Online payment via Razorpay (webhook)',
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
}
