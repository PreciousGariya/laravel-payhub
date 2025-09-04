<?php

namespace Vendor\Payments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Vendor\Payments\Payment useGateway(string $name)
 * @method static array createOrder(array $data)
 * @method static array charge(array $data)
 * @method static array refund(string $transactionId, array $data = [])
 * @method static bool verifyWebhook(array $payload)
 * @method static array availableGateways()
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'payment';
    }
}
