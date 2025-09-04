<?php

namespace Gokulsingh\LaravelPayhub\Events;

class PaymentRefunded
{
    public function __construct(public array $refund) {}
}
