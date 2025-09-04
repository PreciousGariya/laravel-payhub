<?php

namespace Tests\Feature;

use Tests\TestCase;
use Gokulsingh\LaravelPayhub\Gateways\CashfreeGateway;

class CashfreeGatewayTest extends TestCase
{
    protected CashfreeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new CashfreeGateway();
    }

    /** @test */
    public function it_creates_cashfree_order()
    {
        $order = $this->gateway->createOrder([
            'amount' => 1000,
            'currency' => 'INR',
            'email' => 'test@example.com',
            'phone' => '9999999999',
            'metadata' => ['custom_field' => '123']
        ]);

        $this->assertEquals('order', $order['type']);
        $this->assertEquals('cashfree', $order['gateway']);
        $this->assertArrayHasKey('custom_field', $order['metadata']);
    }

    /** @test */
    public function it_creates_cashfree_charge()
    {
        $charge = $this->gateway->charge([
            'order_id' => 'cf_123',
            'amount' => 1000,
            'currency' => 'INR',
        ]);

        $this->assertEquals('payment', $charge['type']);
        $this->assertEquals('processing', $charge['status']);
    }

    /** @test */
    public function it_creates_cashfree_refund()
    {
        $refund = $this->gateway->refund('txn_123', [
            'amount' => 500,
            'currency' => 'INR',
        ]);

        $this->assertEquals('refund', $refund['type']);
        $this->assertEquals('refunded', $refund['status']);
    }

    /** @test */
    public function it_verifies_cashfree_webhook()
    {
        $this->assertTrue($this->gateway->verifyWebhook(['payload' => 'abc']));
        $this->assertFalse($this->gateway->verifyWebhook([]));
    }
}
