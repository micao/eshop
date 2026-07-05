<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Create a payment session/intent for the order.
     *
     * @param Order $order
     * @param string $paymentMethodType (e.g. 'card', 'bancontact')
     * @return array Contains 'client_secret', 'transaction_id', and redirect parameters if any.
     */
    public function createPaymentIntent(Order $order, string $paymentMethodType): array;

    /**
     * Verify the webhook signature and extract event payload.
     *
     * @param Request $request
     * @return array|null Returns parsed event payload if valid, null otherwise.
     */
    public function verifyWebhook(Request $request): ?array;
}
