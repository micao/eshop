<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'shipping_method_id',
    'order_number',
    'subtotal',
    'shipping_cost',
    'tax',
    'grand_total',
    'status',
    'tracking_number',
    'shipping_label_url',
    'payment_method',
    'payment_status',
    'payment_intent_id',
    'shipping_name',
    'shipping_phone',
    'shipping_address_line_1',
    'shipping_address_line_2',
    'shipping_city',
    'shipping_state_province',
    'shipping_postal_code',
    'shipping_country_code',
])]
class Order extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
