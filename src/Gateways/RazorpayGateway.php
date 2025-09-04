<?php

namespace Gokulsingh\LaravelPayhub\Gateways;

use Gokulsingh\LaravelPayhub\Support\BaseGateway;
use Gokulsingh\LaravelPayhub\Contracts\GatewayInterface;
use Gokulsingh\LaravelPayhub\Traits\LogsTransactions;
use Illuminate\Support\Facades\Http;

class RazorpayGateway extends BaseGateway implements GatewayInterface
{
    use LogsTransactions;
    protected string $gatewayName = 'razorpay';
    protected string $base;
    protected string $key;
    protected string $secret;
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->base = $config['base_url'] ?? config('payment.gateways.razorpay.base_url');
        $this->key = $config['key'] ?? config('payment.gateways.razorpay.key');
        $this->secret = $config['secret'] ?? config('payment.gateways.razorpay.secret');
    }
    public function createOrder(array $data): array
    {
        try {
            $payload = ['amount' => (int)(($data['amount'] ?? 0) * 100), 'currency' => $data['currency'] ?? 'INR', 'receipt' => $data['metadata']['receipt'] ?? ($data['receipt'] ?? uniqid('rcpt_')), 'notes' => $data['metadata'] ?? []];
            $resp = Http::withBasicAuth($this->key, $this->secret)->post($this->base . '/orders', $payload);
            if (!$resp->successful()) {
                throw new \Exception('Razorpay API error: ' . $resp->body());
            }
            $r = $resp->json();
            $norm = $this->normalize($r, ['id' => 'id', 'type' => 'order', 'amount' => 'amount', 'currency' => 'currency', 'status' => 'status', 'gateway' => 'razorpay', 'custom' => ['notes' => 'notes', 'receipt' => 'receipt']]);
            $this->logTransaction('razorpay', 'order', $norm['status'], $norm);
            return $this->success(array_merge($norm, ['metadata' => $r] ?? []));
        } catch (\Throwable $e) {
            return $this->handleException($e, 'createOrder');
        }
    }
    public function charge(array $data): array
    {
        try {
            $paymentId = $data['payment_id'] ?? null;
            if (!$paymentId) throw new \InvalidArgumentException('payment_id required');
            $resp = Http::withBasicAuth($this->key, $this->secret)->get($this->base . '/payments/' . $paymentId);
            if (!$resp->successful()) throw new \Exception('Razorpay API error: ' . $resp->body());
            $r = $resp->json();
            $norm = $this->normalize($r, ['id' => 'id', 'type' => 'payment', 'amount' => 'amount', 'currency' => 'currency', 'status' => 'status', 'gateway' => 'razorpay']);
            $this->logTransaction('razorpay', 'charge', $norm['status'], $norm);
            return $this->success($norm);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'charge');
        }
    }
    public function refund(string $transactionId, array $data = []): array
    {
        try {
            $amount = isset($data['amount']) ? (int)($data['amount'] * 100) : null;
            $resp = Http::withBasicAuth($this->key, $this->secret)->post($this->base . "/payments/{$transactionId}/refund", array_filter(['amount' => $amount]));
            if (!$resp->successful()) throw new \Exception('Razorpay API error: ' . $resp->body());
            $r = $resp->json();
            $norm = ['id' => $r['id'] ?? null, 'type' => 'refund', 'amount' => $r['amount'] ?? 0, 'currency' => $r['currency'] ?? 'INR', 'status' => 'refunded', 'gateway' => 'razorpay', 'raw' => $r, 'metadata' => []];
            $this->logTransaction('razorpay', 'refund', $norm['status'], $norm);
            return $this->success($norm);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'refund');
        }
    }
    public function verifyWebhook(array $payload): bool
    {
        try {
            $body = $payload['payload'] ?? '';
            $sig = $payload['headers']['x-razorpay-signature'][0]
                ?? ($payload['headers']['X-Razorpay-Signature'][0] ?? null);

            if (!$sig) {
                return false;
            }

            $webhookSecret = $this->config['webhook_secret']
                ?? config('payment.gateways.razorpay.webhook_secret');

            if (!$webhookSecret) {
                throw new \Exception('Razorpay webhook_secret not configured.');
            }

            $expected = hash_hmac('sha256', $body, $webhookSecret);

            return hash_equals($expected, $sig);
        } catch (\Throwable $e) {
            return false;
        }
    }


    public function getOrders(array $filters = []): array
    {
        try {
            $resp = Http::withBasicAuth($this->key, $this->secret)
                ->get($this->base . '/orders', $filters);

            if (!$resp->successful()) {
                throw new \Exception('Razorpay API error: ' . $resp->body());
            }

            return $this->success([
                'type'   => 'orders',
                'data'   => $resp->json()['items'] ?? [],
                'count'  => $resp->json()['count'] ?? 0,
                'gateway' => 'razorpay'
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getOrders');
        }
    }

    public function getOrder(string $orderId): array
    {
        try {
            $resp = Http::withBasicAuth($this->key, $this->secret)
                ->get($this->base . "/orders/{$orderId}");

            if (!$resp->successful()) {
                throw new \Exception('Razorpay API error: ' . $resp->body());
            }

            $r = $resp->json();
            return $this->success($this->normalize($r, [
                'id' => 'id',
                'type' => 'order',
                'amount' => 'amount',
                'currency' => 'currency',
                'status' => 'status',
                'gateway' => 'razorpay'
            ]));
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getOrder');
        }
    }

    public function getPayments(array $filters = []): array
    {
        try {
            $resp = Http::withBasicAuth($this->key, $this->secret)
                ->get($this->base . '/payments', $filters);

            if (!$resp->successful()) {
                throw new \Exception('Razorpay API error: ' . $resp->body());
            }

            return $this->success([
                'type'   => 'payments',
                'data'   => $resp->json()['items'] ?? [],
                'count'  => $resp->json()['count'] ?? 0,
                'gateway' => 'razorpay'
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getPayments');
        }
    }

    public function getPayment(string $paymentId): array
    {
        try {
            $resp = Http::withBasicAuth($this->key, $this->secret)
                ->get($this->base . "/payments/{$paymentId}");

            if (!$resp->successful()) {
                throw new \Exception('Razorpay API error: ' . $resp->body());
            }

            $r = $resp->json();
            return $this->success($this->normalize($r, [
                'id' => 'id',
                'type' => 'payment',
                'amount' => 'amount',
                'currency' => 'currency',
                'status' => 'status',
                'gateway' => 'razorpay'
            ]));
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getPayment');
        }
    }

    public function getInvoices(array $filters = []): array
    {
        try {
            $resp = Http::withBasicAuth($this->key, $this->secret)
                ->get($this->base . '/invoices', $filters);

            if (!$resp->successful()) {
                throw new \Exception('Razorpay API error: ' . $resp->body());
            }

            return $this->success([
                'type'   => 'invoices',
                'data'   => $resp->json()['items'] ?? [],
                'count'  => $resp->json()['count'] ?? 0,
                'gateway' => 'razorpay'
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getInvoices');
        }
    }

    public function getInvoice(string $invoiceId): array
    {
        try {
            $resp = Http::withBasicAuth($this->key, $this->secret)
                ->get($this->base . "/invoices/{$invoiceId}");

            if (!$resp->successful()) {
                throw new \Exception('Razorpay API error: ' . $resp->body());
            }

            return $this->success([
                'type'   => 'invoice',
                'data'   => $resp->json(),
                'gateway' => 'razorpay'
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getInvoice');
        }
    }

    public function getSettlements(array $filters = []): array
    {
        try {
            $resp = Http::withBasicAuth($this->key, $this->secret)
                ->get($this->base . '/settlements', $filters);

            if (!$resp->successful()) {
                throw new \Exception('Razorpay API error: ' . $resp->body());
            }

            return $this->success([
                'type'   => 'settlements',
                'data'   => $resp->json()['items'] ?? [],
                'count'  => $resp->json()['count'] ?? 0,
                'gateway' => 'razorpay'
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getSettlements');
        }
    }

    public function getSettlement(string $id): array
    {
        try {
            $resp = Http::withBasicAuth($this->key, $this->secret)
                ->get($this->base . "/settlements/{$id}");

            if (!$resp->successful()) {
                throw new \Exception('Razorpay API error: ' . $resp->body());
            }

            return $this->success([
                'type'   => 'settlement',
                'data'   => $resp->json(),
                'gateway' => 'razorpay'
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getSettlement');
        }
    }
}
