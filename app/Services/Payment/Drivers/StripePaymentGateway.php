<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripePaymentGateway implements PaymentGatewayInterface
{
    protected string $secretKey;

    protected string $webhookSecret;

    public function __construct(array $config)
    {
        $this->secretKey = $config['secret_key'] ?? '';
        $this->webhookSecret = $config['webhook_secret'] ?? '';
    }

    /**
     * Create a payment intent on Stripe.
     */
    public function createPaymentIntent(Order $order, string $paymentMethodType): array
    {
        if (empty($this->secretKey)) {
            Log::warning('Stripe API Key is empty. Defaulting to mock response.');

            return [
                'client_secret' => 'stripe_mock_secret_'.$order->order_number,
                'transaction_id' => 'ch_mock_'.uniqid(),
            ];
        }

        $amountInCents = intval(round(floatval($order->grand_total) * 100));

        // Stripe expects payment methods as array elements
        $paymentMethods = [$paymentMethodType];
        if ($paymentMethodType === 'bancontact') {
            // Bancontact on Stripe often requires a fallback or card method, or we specify bancontact
            $paymentMethods = ['bancontact'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->secretKey,
        ])->asForm()->post('https://api.stripe.com/v1/payment_intents', [
            'amount' => $amountInCents,
            'currency' => 'eur',
            'payment_method_types' => $paymentMethods,
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ]);

        if ($response->failed()) {
            Log::error('Stripe PaymentIntent creation failed: '.$response->body());
            throw new \RuntimeException('Stripe payment initialization error: '.($response->json('error.message') ?? 'Unknown error'));
        }

        return [
            'client_secret' => $response->json('client_secret'),
            'transaction_id' => $response->json('id'),
        ];
    }

    /**
     * Verify Stripe webhook signature.
     */
    public function verifyWebhook(Request $request): ?array
    {
        $signature = $request->header('Stripe-Signature');
        $payload = $request->getContent();

        if (empty($signature) || empty($this->webhookSecret)) {
            // Fallback for local testing/development
            $data = $request->json()->all();
            if (isset($data['type']) && isset($data['data']['object']['id'])) {
                return [
                    'event' => $data['type'],
                    'payment_intent_id' => $data['data']['object']['id'],
                ];
            }

            return null;
        }

        // Validate webhook signature manually
        if (! $this->isSignatureValid($payload, $signature)) {
            Log::warning('Invalid Stripe webhook signature.');

            return null;
        }

        $data = json_decode($payload, true);

        return [
            'event' => $data['type'] ?? '',
            'payment_intent_id' => $data['data']['object']['id'] ?? '',
        ];
    }

    /**
     * Helper to parse and check Stripe-Signature header.
     */
    protected function isSignatureValid(string $payload, string $signatureHeader): bool
    {
        // Stripe-Signature format: t=1492774577,v1=co_...,v0=...
        $parts = explode(',', $signatureHeader);
        $timestamp = null;
        $signatures = [];

        foreach ($parts as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                if ($kv[0] === 't') {
                    $timestamp = $kv[1];
                } elseif ($kv[0] === 'v1') {
                    $signatures[] = $kv[1];
                }
            }
        }

        if (! $timestamp || empty($signatures)) {
            return false;
        }

        // Verify within 5-minute drift window
        if (abs(time() - intval($timestamp)) > 300) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
    }
}
