<?php

namespace Gokulsingh\LaravelPayhub\Gateways;

use Vendor\Payments\Support\BaseGateway;
use Vendor\Payments\Contracts\GatewayInterface;
use Vendor\Payments\Traits\LogsTransactions;

class RazorpayGateway extends BaseGateway implements GatewayInterface
{
    use LogsTransactions;

    protected string $gatewayName = 'razorpay';

    public function createOrder(array $data): array
    {
        try {
            // Placeholder for actual SDK call
            $resp = [
                'id' => 'order_' . uniqid(),
                'amount' => (int) (($data['amount'] ?? 0) * 100),
                'currency' => $data['currency'] ?? 'INR',
                'status' => 'created',
                'receipt' => $data['receipt'] ?? null,
            ];

            $norm = $this->normalize($resp, [
                'id' => 'id',
                'type' => 'order',
                'amount' => 'amount',
                'currency' => 'currency',
                'status' => 'status',
                'gateway' => 'razorpay',
                'custom' => ['receipt' => 'receipt'],
            ]);

            $this->logTransaction('razorpay', 'order', $norm['status'], $norm);
            return $this->success($norm);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'createOrder');
        }
    }

    public function charge(array $data): array
    {
        try {
            $resp = [
                'id' => $data['payment_id'] ?? ('pay_' . uniqid()),
                'amount' => (int) ($data['amount'] ?? 0),
                'currency' => $data['currency'] ?? 'INR',
                'status' => 'captured',
            ];

            $norm = $this->normalize($resp, [
                'id' => 'id',
                'type' => 'payment',
                'amount' => 'amount',
                'currency' => 'currency',
                'status' => 'status',
                'gateway' => 'razorpay',
            ]);

            $this->logTransaction('razorpay', 'charge', $norm['status'], $norm);
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
                'gateway' => 'razorpay',
                'raw' => $resp,
                'metadata' => ['transaction_id' => $transactionId],
            ];

            $this->logTransaction('razorpay', 'refund', $norm['status'], $norm);
            return $this->success($norm);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'refund');
        }
    }

    public function verifyWebhook(array $payload): bool
    {
        // Simplified verification; implement real HMAC check with secret for production
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
