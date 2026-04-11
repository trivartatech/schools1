<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayService
{
    protected string $keyId;
    protected string $keySecret;
    protected string $baseUrl = 'https://api.razorpay.com/v1';

    public function __construct()
    {
        $this->keyId     = config('payment.razorpay.key_id');
        $this->keySecret = config('payment.razorpay.key_secret');
    }

    /**
     * Create a Razorpay Order.
     *
     * @param int    $amountPaise  Amount in paise (smallest currency unit)
     * @param string $currency     INR
     * @param string $receipt      Internal receipt/reference ID
     * @param array  $notes        Optional metadata
     * @return array|null          Razorpay order response or null on failure
     */
    public function createOrder(int $amountPaise, string $currency = 'INR', string $receipt = '', array $notes = []): ?array
    {
        try {
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->post("{$this->baseUrl}/orders", [
                    'amount'   => $amountPaise,
                    'currency' => $currency,
                    'receipt'  => $receipt,
                    'notes'    => $notes,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Razorpay createOrder failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('Razorpay createOrder exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Verify payment signature (server-side verification).
     *
     * @param string $orderId    Razorpay order_id
     * @param string $paymentId  Razorpay payment_id
     * @param string $signature  Razorpay signature from checkout
     * @return bool
     */
    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $payload  = $orderId . '|' . $paymentId;
        $expected = hash_hmac('sha256', $payload, $this->keySecret);

        return hash_equals($expected, $signature);
    }

    /**
     * Verify webhook signature.
     *
     * @param string $payload   Raw request body
     * @param string $signature X-Razorpay-Signature header
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $webhookSecret = config('payment.razorpay.webhook_secret');

        if (empty($webhookSecret)) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($expected, $signature);
    }

    /**
     * Fetch a payment's details from Razorpay.
     */
    public function fetchPayment(string $paymentId): ?array
    {
        try {
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->get("{$this->baseUrl}/payments/{$paymentId}");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::error('Razorpay fetchPayment exception', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
