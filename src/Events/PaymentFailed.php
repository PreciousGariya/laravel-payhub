<?php

namespace Vendor\Payments\Events;

class PaymentFailed
{
    public function __construct(public string $reason, public array $context = []) {}
}
