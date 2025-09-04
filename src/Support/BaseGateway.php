<?php

namespace Vendor\Payments\Support;

use Illuminate\Support\Facades\Log;

abstract class BaseGateway extends BaseNormalizer
{
    protected array $config = [];
    protected string $gatewayName = 'unknown';

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('stack')->{$level}($message, $context);
        } catch (\Throwable $e) {
            // swallow logging errors
        }
    }

    protected function handleException(\Throwable $e, string $context = ''): array
    {
        $this->log('error', "[{$this->gatewayName}] {$context}: ".$e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'gateway' => $this->gatewayName,
        ];
    }

    protected function success(array $data): array
    {
        return [
            'success' => true,
            'data' => $data,
            'gateway' => $this->gatewayName,
        ];
    }
}
