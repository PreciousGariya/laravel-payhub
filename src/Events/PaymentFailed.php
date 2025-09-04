<?php

namespace Gokulsingh\LaravelPayhub\Events;

class PaymentFailed
{
    public function __construct(public string $reason, public array $context = []) {}
}
