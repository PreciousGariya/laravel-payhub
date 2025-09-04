<?php
return [
  'default' => env('PAYMENT_GATEWAY', 'razorpay'),
  'logging' => ['enabled' => env('PAYMENT_LOGGING_ENABLED', false)],
  'gateways' => [
    'razorpay' => [
      'enabled' => env('RAZORPAY_ENABLED', true),
      'key' => env('RAZORPAY_KEY'),
      'secret' => env('RAZORPAY_SECRET'),
      'base_url' => env('RAZORPAY_BASE', 'https://api.razorpay.com/v1'),
      'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],
    'cashfree' => [
      'enabled' => env('CASHFREE_ENABLED', true),
      'app_id' => env('CASHFREE_APP_ID'),
      'secret' => env('CASHFREE_SECRET'),
      'mode' => env('CASHFREE_MODE', 'sandbox'),
      'base_url_sandbox' => 'https://sandbox.cashfree.com/pg',
      'base_url_production' => 'https://api.cashfree.com/pg',
      'webhook_secret' => env('CASHFREE_WEBHOOK_SECRET'),
    ],
  ],
];
