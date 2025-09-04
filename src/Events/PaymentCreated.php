<?php

namespace Vendor\Payments\Events;

class PaymentCreated
{
    public function __construct(public array $order) {}
}
