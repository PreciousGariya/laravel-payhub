<?php

namespace Vendor\Payments\Traits;

use Vendor\Payments\Models\PaymentTransaction;

trait LogsTransactions
{
    protected function logTransaction(string $gateway, string $type, string $status, array $payload = []): void
    {
        if (! config('payment.logging.enabled')) {
            return;
        }

        try {
            PaymentTransaction::create([
                'gateway' => $gateway,
                'type' => $type,
                'status' => $status,
                'transaction_id' => $payload['id'] ?? null,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            // ignore logging fails
        }
    }
}
