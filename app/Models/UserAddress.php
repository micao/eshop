<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'recipient_name',
    'recipient_phone',
    'address_line_1',
    'address_line_2',
    'city',
    'state_province',
    'postal_code',
    'country_code',
    'is_default',
])]
class UserAddress extends Model
{
    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
