<?php

namespace Tests\Feature;

use Tests\TestCase;
use Gokulsingh\LaravelPayhub\Gateways\RazorpayGateway;

class RazorpayGatewayTest extends TestCase
{
    protected RazorpayGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new RazorpayGateway();
    }

    /** @test */
    public function it_creates_razorpay_order()
    {
        $order = $this->gateway->createOrder([
            'amount' => 1500,
            'currency' => 'INR',
            'metadata' => ['campaign' => 'Diwali2025']
        ]);

        $this->assertEquals('order', $order['type']);
        $this->assertEquals('razorpay', $order['gateway']);
        $this->assertArrayHasKey('campaign', $order['metadata']);
    }

    /** @test */
    public function it_verifies_webhook()
    {
        $payload = '{"id":"order_123"}';
        $signature = hash_hmac('sha256', $payload, 'test_secret');

        $this->assertTrue(
            $this->gateway->verifyWebhook(
                $payload,
                $signature,
                'test_secret'
            )
        );
    }
}
