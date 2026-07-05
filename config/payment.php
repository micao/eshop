<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment driver that will be used to
    | process checkout orders. Supported: "stripe", "mock".
    |
    */

    'default' => env('PAYMENT_DRIVER', 'mock'),
];
