<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MockPaymentGateway implements PaymentGatewayInterface
{
    /**
     * Create a mock payment intent.
     */
    public function createPaymentIntent(Order $order, string $paymentMethodType): array
    {
        $intentId = 'pi_mock_'.strtolower(Str::random(16));

        return [
            'client_secret' => $intentId.'_secret_'.strtolower(Str::random(8)),
            'transaction_id' => $intentId,
            'redirect_url' => 'https://eshop.test/checkout/pay/'.$intentId,
        ];
    }

    /**
     * Verify the webhook payload.
     */
    public function verifyWebhook(Request $request): ?array
    {
        // For testing/mocking, simply return the JSON payload if present
        $payload = $request->json()->all();
        if (isset($payload['event']) && isset($payload['payment_intent_id'])) {
            return [
                'event' => $payload['event'],
                'payment_intent_id' => $payload['payment_intent_id'],
            ];
        }

        return null;
    }
}
