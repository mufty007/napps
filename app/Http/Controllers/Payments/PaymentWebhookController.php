<?php

namespace App\Http\Controllers\Payments;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Jobs\VerifyGatewayTransaction;
use App\Models\GatewayTransaction;
use App\Services\Payments\InvalidWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Signed gateway notifications. Answers fast and verifies on the queue
 * (with retries); the payload itself is never trusted for amounts.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway): Response
    {
        try {
            $reference = $gateway->webhookReference($request);
        } catch (InvalidWebhookSignature) {
            return response('Invalid signature', 401);
        }

        if ($reference !== null) {
            $transaction = GatewayTransaction::query()->where('reference', $reference)->first();

            if ($transaction !== null && ! $transaction->status->isFinal()) {
                VerifyGatewayTransaction::dispatch($transaction);
            }
        }

        return response('OK', 200);
    }
}
