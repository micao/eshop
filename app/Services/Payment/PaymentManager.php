<?php

namespace App\Services\Payment;

use App\Services\Payment\Drivers\MockPaymentGateway;
use App\Services\Payment\Drivers\StripePaymentGateway;
use Illuminate\Support\Manager;

class PaymentManager extends Manager
{
    /**
     * Get the default payment driver.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return config('payment.default', 'mock');
    }

    /**
     * Create Mock driver.
     *
     * @return MockPaymentGateway
     */
    public function createMockDriver()
    {
        return new MockPaymentGateway;
    }

    /**
     * Create Stripe driver.
     *
     * @return StripePaymentGateway
     */
    public function createStripeDriver()
    {
        return new StripePaymentGateway(config('services.stripe', [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ]));
    }
}
