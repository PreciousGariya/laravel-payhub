<?php

namespace Gokulsingh\LaravelPayhub\Gateways;

use Gokulsingh\LaravelPayhub\Support\BaseGateway;
use Gokulsingh\LaravelPayhub\Contracts\GatewayInterface;
use Gokulsingh\LaravelPayhub\Traits\LogsTransactions;
use Illuminate\Support\Facades\Http;

class CashfreeGateway extends BaseGateway implements GatewayInterface
{
    use LogsTransactions;

    protected string $gatewayName = 'cashfree';
    protected string $appId;
    protected string $secret;
    protected string $base;
    protected string $apiVersion = '2025-01-01';


    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->appId  = $config['app_id'] ?? config('payment.gateways.cashfree.app_id');
        $this->secret = $config['secret'] ?? config('payment.gateways.cashfree.secret');
        $mode         = $config['mode'] ?? config('payment.gateways.cashfree.mode', 'sandbox');
        $this->base   = $mode === 'production'
            ? config('payment.gateways.cashfree.base_url_production')
            : config('payment.gateways.cashfree.base_url_sandbox');
    }

    protected function headers(): array
    {
        return [
            'x-client-id'     => $this->appId,
            'x-api-version'  => $this->apiVersion,
            'x-client-secret' => $this->secret,
            'Content-Type'    => 'application/json',
        ];
    }

    public function createOrder(array $data): array
    {
        try {
            $payload = [
                'order_id'       => isset($data['order_id']) ? $data['order_id'] : null,
                'order_amount'   => (int)($data['amount'] ?? 0),
                'order_currency' => $data['currency'] ?? 'INR',
                'customer_details' => [
                    'customer_id'    => $data['customer_id'] ?? uniqid('cust_'),
                    'customer_name' => $data['name'] ?? null,
                    'customer_email' => $data['email'] ?? null,
                    'customer_phone' => $data['phone'] ?? null,
                ],
                'metadata' => $data['metadata'] ?? [],
                'order_tags' => $data['order_tags'] ?? [],
                'order_note' => $data['order_note'] ?? null,
                'order_expiry' => $data['order_expiry'] ?? null,
            ];

            $resp = Http::withHeaders($this->headers())
                ->post($this->base . '/orders', $payload);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            $r = $resp->json();
            $norm = $this->normalize($r, [
                'id'       => 'order_id',
                'type'     => 'order',
                'amount'   => 'order_amount',
                'currency' => 'order_currency',
                'status'   => 'order_status',
                'gateway'  => 'cashfree',
                'custom'   => ['payment_link' => 'payment_link'],
            ]);

            $this->logTransaction('cashfree', 'order', $norm['status'], $norm);

            return $this->success($norm);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'createOrder');
        }
    }

    public function charge(array $data): array
    {
        try {
            $orderId = $data['order_id'] ?? null;
            if (!$orderId) {
                throw new \InvalidArgumentException('order_id required');
            }

            $resp = Http::withHeaders($this->headers())
                ->get($this->base . '/orders/' . $orderId);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            $r = $resp->json();
            $norm = $this->normalize($r, [
                'id'       => 'order_id',
                'type'     => 'payment',
                'amount'   => 'order_amount',
                'currency' => 'order_currency',
                'status'   => 'order_status',
                'gateway'  => 'cashfree',
            ]);

            $this->logTransaction('cashfree', 'charge', $norm['status'], $norm);

            return $this->success($norm);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'charge');
        }
    }

    public function refund(string $transactionId, array $data = []): array
    {
        try {
            $payload = [
                'refund_amount' => $data['amount'] ?? 0,
                'refund_note'   => $data['note'] ?? 'Refund',
            ];

            $resp = Http::withHeaders($this->headers())
                ->post($this->base . '/orders/' . $transactionId . '/refunds', $payload);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            $r = $resp->json();
            $norm = [
                'id'       => $r['refund_id'] ?? null,
                'type'     => 'refund',
                'amount'   => $r['refund_amount'] ?? 0,
                'currency' => $r['currency'] ?? 'INR',
                'status'   => $r['refund_status'] ?? 'processing',
                'gateway'  => 'cashfree',
                'raw'      => $r,
                'metadata' => [],
            ];

            $this->logTransaction('cashfree', 'refund', $norm['status'], $norm);

            return $this->success($norm);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'refund');
        }
    }

    /**
 * Verify incoming Cashfree webhook request
 *
 * @param array $payload Array containing 'payload' (JSON string) and 'headers' (request headers)
 * @return bool
 */
public function verifyWebhook(array $payload): bool
{
    try {
        // Get raw JSON body
        $body = $payload['payload'] ?? '';

        // Get the signature from headers (Cashfree sends it as x-webhook-signature)
        $sig = $payload['headers']['x-webhook-signature'][0]
            ?? ($payload['headers']['X-WEBHOOK-SIGNATURE'][0] ?? null);

        if (!$sig) {
            // No signature found
            return false;
        }

        // Get the webhook secret from config or gateway instance
        $webhookSecret = $this->secret
            ?? config('payment.gateways.cashfree.webhook_secret');

        if (!$webhookSecret) {
            throw new \Exception('Cashfree webhook_secret not configured.');
        }

        // Compute expected signature using HMAC-SHA256
        $expected = base64_encode(hash_hmac('sha256', $body, $webhookSecret, true));

        // Compare received signature with expected signature
        return hash_equals($expected, $sig);

    } catch (\Throwable $e) {
        // Any error means verification failed
        return false;
    }

    }
    /* -----------------------
     * Additional Resource Methods
     * ----------------------- */

    public function getOrders(array $filters = []): array
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->get($this->base . '/orders', $filters);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            return $this->success($resp->json());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getOrders');
        }
    }

    public function getOrder(string $orderId): array
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->get($this->base . '/orders/' . $orderId);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            return $this->success($resp->json());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getOrder');
        }
    }

    public function getPayments(array $filters = []): array
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->get($this->base . '/payments', $filters);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            return $this->success($resp->json());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getPayments');
        }
    }

    public function getPayment(string $paymentId): array
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->get($this->base . '/payments/' . $paymentId);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            return $this->success($resp->json());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getPayment');
        }
    }

    public function getInvoices(array $filters = []): array
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->get($this->base . '/invoices', $filters);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            return $this->success($resp->json());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getInvoices');
        }
    }

    public function getInvoice(string $invoiceId): array
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->get($this->base . '/invoices/' . $invoiceId);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            return $this->success($resp->json());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getInvoice');
        }
    }

    public function getSettlements(array $filters = []): array
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->get($this->base . '/settlements', $filters);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            return $this->success($resp->json());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getSettlements');
        }
    }

    public function getSettlement(string $orderId): array
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->get($this->base . '/settlements/' . $orderId);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            return $this->success($resp->json());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getSettlement');
        }
    }
    // PG Reconciliation
    /**
     * PG Reconciliation API
     *
     * This endpoint is used to reconcile payments and settlements for a given date range.
     * You can filter results using query parameters like:
     * - start_date: (string, required) ISO8601 date-time for reconciliation start
     * - end_date:   (string, required) ISO8601 date-time for reconciliation end
     * - type:       (string, optional) Type of entity to reconcile ["PAYMENT", "SETTLEMENT"]
     * - entity:     (string, optional) Entity filter ["ORDER", "REFUND", "PAYMENT"]
     *
     * Docs: https://www.cashfree.com/docs/api-reference/payments/latest/settlements/reconcile
     *
     * Example:
     * $gateway->getReconciliation([
     *     'start_date' => '2025-09-01T00:00:00Z',
     *     'end_date'   => '2025-09-30T23:59:59Z',
     *     'entity'     => 'PAYMENT'
     * ]);
     *
     * @param array $filters Query parameters for reconciliation API
     * @return array Normalized API response
     */
    public function getReconciliation(array $filters = []): array
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->get($this->base . '/settlements/reconcile', $filters);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            $result = $resp->json();

            // Normalize if you want a uniform structure
            $norm = [
                'type'   => 'reconciliation',
                'status' => 'success',
                'data'   => $result['data'] ?? [],
                'meta'   => [
                    'start_date' => $filters['start_date'] ?? null,
                    'end_date'   => $filters['end_date'] ?? null,
                ],
                'raw'    => $result,
            ];

            return $this->success($norm);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'getReconciliation');
        }
    }

        /**
     * Settlement Reconciliation – fetch events/details for settlements
     *
     * Docs: https://www.cashfree.com/docs/api-reference/payments/latest/settlements/settlement-reconciliation
     *
     * @param array $body Request body should include:
     *                    - pagination: ['limit' => int, 'cursor' => string|null]
     *                    - filters: [
     *                         'cf_settlement_ids' => [ … ],
     *                         'settlement_utrs' => [ … ],
     *                         'start_date' => ISO8601 datetime,
     *                         'end_date' => ISO8601 datetime,
     *                     ]
     * @return array Normalized settlement reconciliation response
     */
    public function settlementRecon(array $body): array
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->post($this->base . '/settlement/recon', $body);

            if (!$resp->successful()) {
                throw new \Exception('Cashfree API error: ' . $resp->body());
            }

            $r = $resp->json();

            return $this->success($r);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'settlementRecon');
        }
    }
}
