<?php

namespace Vendor\Payments\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'gateway', 'type', 'transaction_id', 'payload', 'status',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
