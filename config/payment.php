<?php

return [
    'default' => env('PAYMENT_GATEWAY', 'razorpay'),

    'logging' => [
        'enabled' => env('PAYMENT_LOGGING', false),
    ],

    'gateways' => [
        'razorpay' => [
            'enabled' => true,
            'key'     => env('RAZORPAY_KEY'),
            'secret'  => env('RAZORPAY_SECRET'),
        ],
        'cashfree' => [
            'enabled' => true,
            'app_id'  => env('CASHFREE_APP_ID'),
            'secret'  => env('CASHFREE_SECRET'),
            'sandbox' => env('CASHFREE_SANDBOX', true),
        ],
    ],
];
