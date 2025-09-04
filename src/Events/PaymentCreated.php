<?php
namespace Gokulsingh\LaravelPayhub\Events;
class PaymentCreated{ public function __construct(public array $order){} }

