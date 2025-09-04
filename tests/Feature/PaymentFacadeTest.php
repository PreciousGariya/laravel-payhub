<?php

namespace Tests\Feature;

use Tests\TestCase;
use Gokulsingh\LaravelPayhub\Facades\Payment;

class PaymentFacadeTest extends TestCase
{
    /** @test */
    public function it_resolves_payment_facade()
    {
        $this->assertInstanceOf(\Gokulsingh\LaravelPayhub\Payment::class, app('payment'));
    }

    /** @test */
    public function it_creates_order_using_default_gateway()
    {
        $order = Payment::createOrder([
            'amount' => 500,
            'currency' => 'INR',
            'metadata' => ['note' => 'Test order'],
        ]);

        $this->assertArrayHasKey('id', $order);
        $this->assertEquals('order', $order['type']);
        $this->assertArrayHasKey('metadata', $order);
        $this->assertEquals('Test order', $order['metadata']['note']);
    }
}
