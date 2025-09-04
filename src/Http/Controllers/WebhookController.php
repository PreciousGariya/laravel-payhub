<?php

namespace Vendor\Payments\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Vendor\Payments\Facades\Payment;
use Vendor\Payments\Events\PaymentSucceeded;
use Vendor\Payments\Events\PaymentFailed;

class WebhookController extends Controller
{
    public function handle(Request $request, string $gateway)
    {
        try {
            $payload = [
                'payload' => $request->getContent(),
                'signature' => $request->header('X-Signature', ''),
                // in real flows, pass gateway-specific headers as well
            ];

            $verified = Payment::useGateway($gateway)->verifyWebhook($payload);

            if (! $verified) {
                return response()->json(['error' => 'Invalid webhook signature'], 400);
            }

            event(new PaymentSucceeded([
                'gateway' => $gateway,
                'payload' => $request->all(),
            ]));

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            event(new PaymentFailed($e->getMessage(), ['gateway' => $gateway]));
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
