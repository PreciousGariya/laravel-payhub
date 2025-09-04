<?php
namespace Gokulsingh\LaravelPayhub\Contracts;
interface GatewayInterface
{
    public function createOrder(array $data): array;
    public function charge(array $data): array;
    public function refund(string $transactionId, array $data = []): array;
    public function verifyWebhook(array $payload): bool;
    public function getOrders(array $filters = []): array;
    public function getOrder(string $orderId): array;
    public function getPayments(array $filters = []): array;
    public function getPayment(string $paymentId): array;
    public function getInvoices(array $filters = []): array;
    public function getInvoice(string $invoiceId): array;
    public function getSettlements(array $filters = []): array;
    public function getSettlement(string $settlementId): array;
}

