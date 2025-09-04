<?php

namespace Gokulsingh\LaravelPayhub;

use Gokulsingh\LaravelPayhub\Contracts\GatewayInterface;
use Gokulsingh\LaravelPayhub\Gateways\RazorpayGateway;
use Gokulsingh\LaravelPayhub\Gateways\CashfreeGateway;
use Gokulsingh\LaravelPayhub\Events\PaymentCreated;
use Gokulsingh\LaravelPayhub\Events\PaymentSucceeded;
use Gokulsingh\LaravelPayhub\Events\PaymentRefunded;
use Gokulsingh\LaravelPayhub\Events\PaymentFailed;
use Illuminate\Support\Facades\Event;

class Payment
{
    protected GatewayInterface $gateway;
    protected string $gatewayName;

    public function __construct()
    {
        $this->useGateway(config('payment.default'));
    }

    public function useGateway(string $name): static
    {
        $config = config("payment.gateways.$name", []);
        $this->gatewayName = $name;

        switch ($name) {
            case 'razorpay':
                $this->gateway = new RazorpayGateway($config);
                break;
            case 'cashfree':
                $this->gateway = new CashfreeGateway($config);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported payment gateway: $name");
        }

        return $this;
    }

    public function getGateway(): GatewayInterface
    {
        return $this->gateway;
    }

    public function createOrder(array $data): array
    {
        try {
            $order = $this->gateway->createOrder($data);
            Event::dispatch(new PaymentCreated($order));
            return $order;
        } catch (\Throwable $e) {
            Event::dispatch(new PaymentFailed($e->getMessage(), ['operation' => 'createOrder', 'data' => $data]));
            throw $e;
        }
    }

    public function charge(array $data): array
    {
        try {
            $payment = $this->gateway->charge($data);
            Event::dispatch(new PaymentSucceeded($payment));
            return $payment;
        } catch (\Throwable $e) {
            Event::dispatch(new PaymentFailed($e->getMessage(), ['operation' => 'charge', 'data' => $data]));
            throw $e;
        }
    }

    public function refund(string $transactionId, array $data = []): array
    {
        try {
            $refund = $this->gateway->refund($transactionId, $data);
            Event::dispatch(new PaymentRefunded($refund));
            return $refund;
        } catch (\Throwable $e) {
            Event::dispatch(new PaymentFailed($e->getMessage(), ['operation' => 'refund', 'data' => $data]));
            throw $e;
        }
    }

    public function verifyWebhook(array $payload): bool
    {
        return $this->gateway->verifyWebhook($payload);
    }

    // Discovery helpers (config-mode only)
    public function availableGateways(): array
    {
        return collect(config('payment.gateways'))
            ->filter(fn ($g) => $g['enabled'] ?? false)
            ->keys()
            ->toArray();
    }

    // Extended methods proxying to driver
    public function __call($method, $arguments)
    {
        if (method_exists($this->gateway, $method)) {
            return $this->gateway->{$method}(...$arguments);
        }
        throw new \BadMethodCallException("Method {$method} does not exist on gateway {$this->gatewayName}.");
    }
}
