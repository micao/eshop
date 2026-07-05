<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Drivers\FlatRateDriver;
use Illuminate\Support\Manager;

class ShippingManager extends Manager
{
    /**
     * Get default shipping driver.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return 'flat_rate';
    }

    /**
     * Create FlatRate driver.
     *
     * @return FlatRateDriver
     */
    public function createFlatRateDriver()
    {
        return new FlatRateDriver();
    }
}
