<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'name',
    'carrier',
    'gateway_driver',
    'base_price',
    'is_active',
])]
class ShippingMethod extends Model
{
    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
