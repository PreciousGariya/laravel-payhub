<?php

namespace Vendor\Payments\Events;

class PaymentSucceeded
{
    public function __construct(public array $payment) {}
}
