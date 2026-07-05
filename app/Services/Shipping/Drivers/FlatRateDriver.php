<?php

namespace App\Services\Shipping\Drivers;

use App\Services\Shipping\ShippingGatewayInterface;
use Illuminate\Support\Str;

class FlatRateDriver implements ShippingGatewayInterface
{
    /**
     * Compute shipping rates for a destination address and array of cart items.
     *
     * @param array $address
     * @param array $items
     * @param float $basePrice
     * @return array
     */
    public function getRates(array $address, array $items, float $basePrice): array
    {
        // Compute subtotal of items
        $subtotal = 0;
        foreach ($items as $item) {
            $price = $item['price'] ?? 0;
            $qty = $item['quantity'] ?? 0;
            $subtotal += ($price * $qty);
        }

        // Free shipping for orders of 50 EUR or more
        if ($subtotal >= 50.00) {
            return [
                'cost' => 0.00,
                'currency' => 'EUR',
                'delivery_days' => 3,
            ];
        }

        $countryCode = strtoupper($address['country_code'] ?? 'BE');
        $cost = $basePrice;

        // If international within EU, apply standard premium, otherwise double it
        if ($countryCode !== 'BE') {
            $cost = $basePrice + 5.00;
        }

        return [
            'cost' => floatval($cost),
            'currency' => 'EUR',
            'delivery_days' => $countryCode === 'BE' ? 1 : 4,
        ];
    }

    /**
     * Create a shipment label and register package on the carrier network.
     *
     * @param array $orderData
     * @return array
     */
    public function createShipment(array $orderData): array
    {
        $trackingNumber = 'TRK-' . strtoupper(Str::random(12));
        return [
            'tracking_number' => $trackingNumber,
            'label_url' => 'https://eshop.test/shipping/labels/' . $trackingNumber . '.pdf',
            'status' => 'registered',
        ];
    }
}
