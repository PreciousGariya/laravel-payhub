<?php
namespace Gokulsingh\LaravelPayhub;
use Gokulsingh\LaravelPayhub\Gateways\RazorpayGateway;
use Gokulsingh\LaravelPayhub\Gateways\CashfreeGateway;
use Gokulsingh\LaravelPayhub\Events\PaymentCreated;
use Gokulsingh\LaravelPayhub\Events\PaymentSucceeded;
use Gokulsingh\LaravelPayhub\Events\PaymentRefunded;
use Gokulsingh\LaravelPayhub\Events\PaymentFailed;
use Illuminate\Support\Facades\Event;
class Payment
{
    protected $gateway;
    protected string $gatewayName = '';
    public function __construct(){ $this->gateway(config('payment.default','razorpay')); }
    public function gateway(string $name): static
    {
        $name = strtolower($name);
        $this->gatewayName = $name;
        $config = config("payment.gateways.$name", []);
        return match ($name) {
            'razorpay' => $this->setGateway(new RazorpayGateway($config)),
            'cashfree' => $this->setGateway(new CashfreeGateway($config)),
            default => throw new \InvalidArgumentException("Unsupported gateway: $name"),
        };
    }
    protected function setGateway($g): static{ $this->gateway=$g; return $this; }
    public function getGateway(){ return $this->gateway; }
    public function createOrder(array $data): array
    {
        try{
            $data['metadata'] = $data['metadata'] ?? [];
            $order = $this->gateway->createOrder($data);
            Event::dispatch(new PaymentCreated($order));
            return $order;
        }catch(\Throwable $e){
            Event::dispatch(new PaymentFailed($e->getMessage(),['operation'=>'createOrder','data'=>$data]));
            throw $e;
        }
    }
    public function charge(array $data): array
    {
        try{
            $payment = $this->gateway->charge($data);
            Event::dispatch(new PaymentSucceeded($payment));
            return $payment;
        }catch(\Throwable $e){
            Event::dispatch(new PaymentFailed($e->getMessage(),['operation'=>'charge','data'=>$data]));
            throw $e;
        }
    }
    public function refund(string $transactionId, array $data = []): array
    {
        try{
            $refund = $this->gateway->refund($transactionId, $data);
            Event::dispatch(new PaymentRefunded($refund));
            return $refund;
        }catch(\Throwable $e){
            Event::dispatch(new PaymentFailed($e->getMessage(),['operation'=>'refund','data'=>$data]));
            throw $e;
        }
    }
    public function verifyWebhook(array $payload): bool{ return $this->gateway->verifyWebhook($payload); }
    public function availableGateways(): array{ return collect(config('payment.gateways'))->filter(fn($g)=>$g['enabled']??false)->keys()->toArray(); }
    public function __call($method,$args){ if(method_exists($this->gateway,$method)) return $this->gateway->{$method}(...$args); throw new \BadMethodCallException("Method {$method} does not exist on gateway {$this->gatewayName}."); }
}

