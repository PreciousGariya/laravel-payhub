<?php

namespace Gokulsingh\LaravelPayhub\Gateways;

use Vendor\Payments\Support\BaseGateway;
use Vendor\Payments\Contracts\GatewayInterface;
use Vendor\Payments\Traits\LogsTransactions;

class CashfreeGateway extends BaseGateway implements GatewayInterface
{
    use LogsTransactions;

    protected string $gatewayName = 'cashfree';

    public function createOrder(array $data): array
    {
        try {
            $resp = [
                'order_id' => 'cf_' . uniqid(),
                'order_amount' => (int) ($data['amount'] ?? 0),
                'order_currency' => $data['currency'] ?? 'INR',
                'status' => 'created',
                'payment_link' => 'https://example.com/pay/' . uniqid(),
            ];

            $norm = $this->normalize($resp, [
                'id' => 'order_id',
                'type' => 'order',
                'amount' => 'order_amount',
                'currency' => 'order_currency',
                'status' => 'status',
                'gateway' => 'cashfree',
                'custom' => ['payment_link' => 'payment_link'],
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
            $resp = [
                'id' => $data['order_id'] ?? ('cf_' . uniqid()),
                'amount' => (int) ($data['amount'] ?? 0),
                'currency' => $data['currency'] ?? 'INR',
                'status' => 'processing',
            ];

            $norm = $this->normalize($resp, [
                'id' => 'id',
                'type' => 'payment',
                'amount' => 'amount',
                'currency' => 'currency',
                'status' => 'status',
                'gateway' => 'cashfree',
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
            $amount = (int) ($data['amount'] ?? 0);
            $resp = [
                'id' => 'rf_' . uniqid(),
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $data['currency'] ?? 'INR',
                'status' => 'refunded',
            ];

            $norm = [
                'id' => $resp['id'],
                'type' => 'refund',
                'amount' => $resp['amount'],
                'currency' => $resp['currency'],
                'status' => 'refunded',
                'gateway' => 'cashfree',
                'raw' => $resp,
                'metadata' => ['transaction_id' => $transactionId],
            ];

            $this->logTransaction('cashfree', 'refund', $norm['status'], $norm);
            return $this->success($norm);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'refund');
        }
    }

    public function verifyWebhook(array $payload): bool
    {
        return !empty($payload['payload']);
    }

    // Optional listings (stubs)
    public function getOrders(array $filters = []): array { return []; }
    public function getOrder(string $orderId): array { return []; }
    public function getPayments(array $filters = []): array { return []; }
    public function getPayment(string $paymentId): array { return []; }
    public function getInvoices(array $filters = []): array { return []; }
    public function getInvoice(string $invoiceId): array { return []; }
    public function getSettlements(array $filters = []): array { return []; }
    public function getSettlement(string $settlementId): array { return []; }
}
