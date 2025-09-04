# Laravel PayHub — Step-by-step Guide (Beginner Friendly)

A unified payment wrapper for Laravel supporting **Razorpay** and **Cashfree**.
It provides a consistent API: create orders, charge, refund, verify webhooks, store optional logs, and attach custom metadata per order.

---

## Table of contents

1. Getting started (Install)
2. Publish config & migrations
3. Configure `.env`
4. Quick examples (backend)
5. Checkout integration (frontend)

   * Razorpay (Checkout.js)
   * Cashfree (Hosted link or Drop-in)
6. Success callback (route + controller + verify)
7. Webhook integration (route + verify)
8. Metadata (custom data per order)
9. Optional DB logging (disable/enable)
10. Unit tests
11. Troubleshooting & FAQ
12. Extending with a new gateway
13. License

---

## 1 — Getting started (Install)

### Option A — Install from Packagist (recommended when published)

```bash
composer require gokulsingh/laravel-payhub
```

### Option B — Local development (path repository)

If you keep the package in your app under `packages/gokulsingh/laravel-payhub`, add to your app `composer.json`:

```json
"repositories": {
  "laravel-payhub": {
    "type": "path",
    "url": "packages/gokulsingh/laravel-payhub"
  }
}
```

Then run:

```bash
composer require gokulsingh/laravel-payhub:* --dev
```

Laravel supports package auto-discovery — no manual provider registration needed.

---

## 2 — Publish config & migrations

Publish config + migration files into your Laravel app:

```bash
php artisan vendor:publish --provider="Gokulsingh\LaravelPayhub\PaymentServiceProvider" --tag=config
php artisan vendor:publish --provider="Gokulsingh\LaravelPayhub\PaymentServiceProvider" --tag=migrations
```

If you want DB logging, run:

```bash
php artisan migrate
```

> Note: If you don’t want the `payment_transactions` table, skip the `migrations` publish and `migrate` step — see "Optional DB logging" below.

---

## 3 — Configure `.env`

Add credentials and options to your `.env`:

```env
# Default gateway
PAYMENT_GATEWAY=razorpay

# Razorpay
RAZORPAY_KEY=rzp_test_xxx
RAZORPAY_SECRET=rzp_secret_xxx
RAZORPAY_WEBHOOK_SECRET=your_rzp_webhook_secret


# Cashfree
CASHFREE_APP_ID=your_cashfree_app_id
CASHFREE_SECRET=your_cashfree_secret
CASHFREE_MODE=sandbox # or production
CASHFREE_WEBHOOK_SECRET=your_cf_webhook_secret


# Logging (DB)
PAYMENT_LOGGING_ENABLED=true
```

Open `config/payment.php` (published) to confirm settings.

**Important — Amount units**

* When calling `createOrder(...)` on this package **pass amount in the main currency unit** (e.g., rupees — `500` = ₹500).

  * Razorpay internally converts ₹ to paise (multiplies by 100).
  * Cashfree uses the amount value directly as provided (e.g., `1200` = ₹1,200).
* Always check the gateway docs when you change behavior.

---

## 4 — Quick backend examples

Use the package via the `Payment` facade.

**Create an order using the default gateway**

```php
use Gokulsingh\LaravelPayhub\Facades\Payment;

$order = Payment::createOrder([
    'amount'   => 500,             // ₹500 (Razorpay will send 50000 paise to API)
    'currency' => 'INR',
    'metadata' => ['receipt' => 'ORD-1001', 'user_id' => auth()->id()],
]);
```

**Explicit gateway**

```php
$orderCF = Payment::gateway('cashfree')->createOrder([
             'order_id' => uniqid('ord_'), //else remove automatic generate
            'amount' => 1500,
            'currency' => 'INR',
            'customer_id' => "3297842",
            'email' => 'v2t9H@example.com',
            'phone' => '9999999999',
            'metadata' => [
                'return_url' => 'https://mysite.domain/return_url', // https url else remove 
                'notify_url' => 'https://mysite.domain/notify_url', // https url else remove 
                'payment_methods' =>  "cc", "dc", "ccc", "ppc","nb","upi","paypal","app","paylater","cardlessemi","dcemi","ccemi", //check for all available options in cashfree documentation 
                "banktransfer"
            ],
            'order_tags' => [
                'note1' => 'note1',
                'note2' => 'note2',
            ]
        ]);
```

**Charge / verify a payment**

```php
// Razorpay verification by payment id from JS
$payment = Payment::gateway('razorpay')->charge([
    'payment_id' => 'pay_XXXXXXXX',
]);

// Cashfree check status by order id
$payment = Payment::gateway('cashfree')->charge([
    'order_id' => 'cf_order_XXXX',
]);
```

**Refund**

```php
$refund = Payment::gateway('razorpay')->refund('pay_XXXXXXXX', ['amount' => 200]);
$refund = Payment::gateway('cashfree')->refund('cf_order_XXXX', ['amount' => 500, 'note' => 'Partial refund']);
```

---

## 5 — Checkout integration (frontend)

After you create an order in backend, integrate frontend to complete payment.

### A — Razorpay (Checkout.js)

**Backend**: create order and return JSON.

```php
// Controller
public function createRazorpayOrder()
{
    $order = Payment::gateway('razorpay')->createOrder([
        'amount'   => 500,
        'currency' => 'INR',
        'metadata' => ['receipt' => 'rzp_order_101'],
    ]);
    return response()->json($order);
}
```

**Frontend**:

```html
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
fetch("/orders/razorpay")
  .then(r => r.json())
  .then(order => {
    const options = {
      key: "{{ config('payment.gateways.razorpay.key') }}",
      amount: order.data.amount,     // numeric, matches createOrder value (Razorpay expects paise but package handles)
      currency: order.data.currency,
      name: "My Store",
      description: "Order #" + (order.data.custom?.receipt || ''),
      order_id: order.data.id,       // <--- required for Razorpay Checkout
      handler: function (response) {
        // send gateway response to server for verification
        fetch("/payment/success", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            gateway: "razorpay",
            payment_id: response.razorpay_payment_id,
          })
        });
      }
    };
    const rzp = new Razorpay(options);
    rzp.open();
  });
</script>
```

Notes:

* `order.data.id` is Razorpay `order_id` — required to initialize Checkout and show payment modal.
* After success, `response.razorpay_payment_id` is provided to backend to verify.

---

### B — Cashfree

**Backend**: create order

```php
public function createCashfreeOrder()
{
    $order = Payment::gateway('cashfree')->createOrder([
            'order_id' => uniqid('ord_'), //else remove automatic generate
            'amount' => 1500,
            'currency' => 'INR',
            'customer_id' => "3297842",
            'email' => 'v2t9H@example.com',
            'phone' => '9999999999',
            'metadata' => [
                'return_url' => 'https://mysite.domain/return_url', // https url else remove 
                'notify_url' => 'https://mysite.domain/notify_url', // https url else remove 
                'payment_methods' =>  "cc", "dc", "ccc", "ppc","nb","upi","paypal","app","paylater","cardlessemi","dcemi","ccemi", //check for all available options in cashfree documentation 
                "banktransfer"
            ],
            'order_tags' => [
                'note1' => 'note1',
                'note2' => 'note2',
            ]
        ]);
    return response()->json($order);
}
```

**Option 1 — Redirect to hosted payment link**
If `createOrder()` returns a `payment_link` (or in `raw`), simply redirect:

```php
return redirect($order['data']['custom']['payment_link'] ?? $order['data']['raw']['payment_link']);
```

**Option 2 — Cashfree Drop-in**

```html
<script src="https://sdk.cashfree.com/js/ui/2.0.0/cashfree.sandbox.js"></script>
<script>
fetch("/orders/cashfree")
.then(r => r.json())
.then(order => {
  const dropin = new Cashfree();
  dropin.initialiseDropin({
    orderToken: order.data.metadata?.order_token ?? order.data.raw?.order_token,
    onSuccess: function(data) {
      fetch("/payment/success", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ gateway: "cashfree", order_id: order.data.id })
      });
    },
    onFailure: function(data) { console.error("Payment failed", data); }
  });
});
</script>
```

Notes:

* Cashfree response shape depends on their API and your account; check the `raw` response in `order.data.raw`.
* If using Drop-in, you must supply `order_token` (returned by Cashfree in the raw response).

---

## 6 — Success callback (server side verification)

Add a route:

```php
// routes/web.php
use App\Http\Controllers\PaymentController;
Route::post('/payment/success', [PaymentController::class, 'success']);
```

Create controller:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Gokulsingh\LaravelPayhub\Facades\Payment;

class PaymentController extends Controller
{
    public function success(Request $request)
    {
        $gateway = $request->input('gateway');

        if ($gateway === 'razorpay') {
            $result = Payment::gateway('razorpay')->charge([
                'payment_id' => $request->input('payment_id'),
            ]);
        } elseif ($gateway === 'cashfree') {
            $result = Payment::gateway('cashfree')->charge([
                'order_id' => $request->input('order_id'),
            ]);
        } else {
            return response()->json(['message' => 'Unsupported gateway'], 400);
        }

        if ($result['success']) {
            // Payment verified — mark order as paid in your DB
            return response()->json(['message' => 'Payment successful', 'data' => $result]);
        }

        return response()->json(['message' => 'Payment verification failed', 'data' => $result], 400);
    }
}
```

This uses the package `charge()` method which calls the gateway API and returns normalized result.

---

## 7 — Webhook integration (automatic route + verification)

Publish routes with the package route macro:

```php
// In routes/web.php (or anywhere routes are loaded)
Route::paymentWebhooks('payment/webhook'); // by default POST /payment/webhook/{gateway}
```

The package `WebhookController` will:

* Collect the raw payload and headers,
* Call `Payment::useGateway($gateway)->verifyWebhook($payload)`,
* Dispatch events on success/failure.

If you need custom behavior, extend `WebhookController` or listen to the package events:

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \Gokulsingh\LaravelPayhub\Events\PaymentSucceeded::class => [
        \App\Listeners\HandlePaymentSucceeded::class,
    ],
];
```

**Manual verification example (Razorpay)**:

```php
$verified = Payment::gateway('razorpay')->verifyWebhook([
    'payload' => file_get_contents('php://input'),
    'headers' => request()->headers->all(),
]);
```

---

## 8 — Custom metadata (attach any data you want)

When creating orders, pass `metadata` array — it will be:

* Sent to the gateway (Razorpay `notes`, Cashfree `metadata`) where supported.
* Stored with the normalized response (check `data.metadata` or `data.raw`).

```php
$order = Payment::gateway('cashfree')->createOrder([
    'amount' => 1500,
    'currency' => 'INR',
    'metadata' => [
        'user_id' => auth()->id(),
        'cart_id' => 999,
        'custom_flag' => 'gift',
    ],
]);
```

Use metadata to store app-specific IDs, tracking info, coupons, etc.

---

## 9 — Optional DB logging

By default the package logs transactions into `payment_transactions`. You can disable this:

**Disable**:

```php
// config/payment.php
'logging' => [
  'enabled' => false,
],
```

If disabled:

* The `LogsTransactions` trait will skip DB writes.
* You can skip publishing the migration or skip running `php artisan migrate`.

If enabled later:

```bash
php artisan vendor:publish --provider="Gokulsingh\LaravelPayhub\PaymentServiceProvider" --tag=migrations
php artisan migrate
```

**Migration fields** typically include: `gateway`, `type`, `status`, `amount`, `currency`, `transaction_id`, `payload` (json), `created_at`.

---

## 10 — Unit tests

The package includes PHPUnit tests (feature tests). Run them from your application:

```bash
php artisan test
# or
./vendor/bin/phpunit
```

Suggested tests:

* Payment facade resolves
* createOrder returns normalized response
* charge() verifies payments
* refund() returns normalized refund

When testing, mock HTTP client responses to avoid hitting real APIs.

---

## 11 — Troubleshooting & FAQ

**Q: `Class not found` after `composer install`?**
A: Run `composer dump-autoload` and ensure package `composer.json` `psr-4` namespace matches your `src/` namespaces.

**Q: `No publishable resources` when vendor\:publish?**
A: Verify you used the correct tag `--tag=config` and `--tag=migrations` (plural). Check the package provider namespace matches installed package.

**Q: Amount mismatches (Razorpay shows ×100)?**
A: Pass amount in main currency unit (₹). The package converts for Razorpay internally.

**Q: Webhook signature verification failing?**
A: Make sure `RAZORPAY_SECRET` / `CASHFREE_SECRET` are correct, and that your webhook body is the exact raw JSON used to compute the HMAC. When testing locally, use `ngrok` to forward webhooks.

---

## 12 — Extending (Add a new gateway)

1. Implement `Gokulsingh\LaravelPayhub\Contracts\GatewayInterface`.
2. Use `BaseGateway` & `BaseNormalizer` for consistent behavior.
3. Register gateway in your `Payment` manager (or modify `Payment::useGateway` switch).
4. Add config values in `config/payment.php`.

---

## 13 — Contributing & License

* Pull requests welcome.
* Follow PSR-12 and write tests for new functionality.
* License: MIT

---