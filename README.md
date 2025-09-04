# Vendor Payments (Unified Wrapper)

Unified Laravel payments package with **Razorpay** and **Cashfree** drivers.
Includes: facade, events, webhook controller, normalization, optional DB logging.

## Install (in your Laravel app)
```bash
# Place this package under packages/vendor/payments (or install via VCS)
composer config repositories.vendor-payments path packages/vendor/payments
composer require vendor/payments:* --dev
php artisan vendor:publish --provider="Vendor\Payments\PaymentServiceProvider" --tag=config
php artisan migrate
```

## Configure
Set your gateway creds in `config/payment.php` or `.env`:

```env
PAYMENT_GATEWAY=razorpay
RAZORPAY_KEY=xxx
RAZORPAY_SECRET=xxx

CASHFREE_APP_ID=xxx
CASHFREE_SECRET=yyy
CASHFREE_SANDBOX=true

PAYMENT_LOGGING=false
```

## Use
```php
use Payment;

// Create order with default gateway
$order = Payment::createOrder(['amount' => 500, 'currency' => 'INR']);

// Switch gateway on the fly
$order = Payment::useGateway('cashfree')->createOrder(['amount' => 200, 'currency' => 'INR']);

// Refund
$refund = Payment::refund('txn_or_order_id', ['amount' => 200]);

// Webhook route
// routes/web.php
Route::paymentWebhooks(); // POST /payment/webhook/{gateway}
```

## Notes
- Gateways return **normalized** structure with `id, type, amount, currency, status, gateway, raw, metadata`.
- This package ships simplified gateway calls for demo/bootstrapping; wire real SDK calls in production.
