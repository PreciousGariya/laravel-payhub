<?php

namespace Vendor\Payments\Events;

class PaymentRefunded
{
    public function __construct(public array $refund) {}
}
