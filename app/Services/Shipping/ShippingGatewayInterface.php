<?php

namespace App\Services\Shipping;

interface ShippingGatewayInterface
{
    /**
     * Compute shipping rates for a destination address and array of cart items.
     */
    public function getRates(array $address, array $items, float $basePrice): array;

    /**
     * Create a shipment label and register package on the carrier network.
     */
    public function createShipment(array $orderData): array;
}
