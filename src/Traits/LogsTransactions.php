<?php

namespace Gokulsingh\LaravelPayhub\Traits;

use Gokulsingh\LaravelPayhub\Models\PaymentTransaction;

trait LogsTransactions
{
    protected function logTransaction(string $gateway, string $type, string $status, array $payload = []): void
    {
        if (! config('payment.logging.enabled', true)) {
            return;
        }

        try {
            PaymentTransaction::create([
                'gateway'        => $gateway,
                'type'           => $type,
                'status'         => $status,
                'amount'         => $payload['amount'] ?? null,
                'currency'       => $payload['currency'] ?? null,
                'transaction_id' => $payload['id'] ?? null,
                'payload'        => $payload,
            ]);
        } catch (\Throwable $e) {
            // We silently fail so logging never breaks payments
            report($e); // optional: log in Laravel logs
        }
    }
}
