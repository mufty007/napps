<?php

namespace App\Contracts;

use App\Models\GatewayTransaction;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\InvalidWebhookSignature;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Http\Request;

/**
 * An online payment provider (Paystack in production). Modules never call a
 * gateway directly: they go through App\Services\Payments\PaymentService and
 * react to the App\Events\PaymentConfirmed event.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Open a checkout for the transaction and return the URL to send the payer to.
     *
     * @throws PaymentGatewayException
     */
    public function initialize(GatewayTransaction $transaction, string $email, string $callbackUrl): string;

    /**
     * Ask the gateway what happened to the transaction.
     *
     * @throws PaymentGatewayException when the gateway cannot be reached (retry later)
     */
    public function verify(GatewayTransaction $transaction): GatewayResult;

    /**
     * Check a webhook's signature and return the reference of a transaction
     * worth re-verifying, or null for events we do not act on.
     *
     * @throws InvalidWebhookSignature
     */
    public function webhookReference(Request $request): ?string;
}
