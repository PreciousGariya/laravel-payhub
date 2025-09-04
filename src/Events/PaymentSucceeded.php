<?php

namespace Gokulsingh\LaravelPayhub\Events;

class PaymentSucceeded
{
    public function __construct(public array $payment) {}
}
