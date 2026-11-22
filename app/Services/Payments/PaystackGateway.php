<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\GatewayStatus;
use App\Models\GatewayTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Paystack (https://paystack.com/docs/api/). Amounts are in kobo.
 *
 * Checkout: POST /transaction/initialize -> redirect the payer to the
 * authorization URL -> Paystack sends them back to our callback and posts a
 * signed charge.success webhook. Both paths re-verify with
 * GET /transaction/verify/{reference} before anything is marked paid.
 */
class PaystackGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $baseUrl = 'https://api.paystack.co',
    ) {}

    public function name(): string
    {
        return 'paystack';
    }

    public function initialize(GatewayTransaction $transaction, string $email, string $callbackUrl): string
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post('/transaction/initialize', [
            'email' => $email,
            'amount' => $transaction->amount_kobo,
            'currency' => $transaction->currency,
            'reference' => $transaction->reference,
            'callback_url' => $callbackUrl,
            'metadata' => [
                'purpose' => $transaction->purpose,
                'transaction_id' => $transaction->getKey(),
                'school_id' => $transaction->school_id,
                'cancel_action' => $callbackUrl.'?reference='.urlencode($transaction->reference),
            ],
        ]));

        $url = $response->json('data.authorization_url');

        if (! $response->successful() || ! $response->json('status') || ! is_string($url)) {
            throw new PaymentGatewayException('Paystack could not start the payment: '.($response->json('message') ?? $response->status()));
        }

        return $url;
    }

    public function verify(GatewayTransaction $transaction): GatewayResult
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('/transaction/verify/'.rawurlencode($transaction->reference)));

        // Paystack answers 400/404 "Transaction reference not found" until the
        // payer has reached the checkout page; that is still "pending".
        if (in_array($response->status(), [400, 404], true)) {
            return new GatewayResult(GatewayStatus::Pending, message: (string) $response->json('message'));
        }

        if (! $response->successful() || ! is_array($data = $response->json('data'))) {
            throw new PaymentGatewayException('Paystack verify failed with HTTP '.$response->status());
        }

        $status = match ($data['status'] ?? null) {
            'success' => GatewayStatus::Success,
            'failed', 'reversed' => GatewayStatus::Failed,
            'abandoned' => GatewayStatus::Abandoned,
            default => GatewayStatus::Pending, // ongoing, pending, processing, queued
        };

        return new GatewayResult(
            status: $status,
            amountKobo: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: $data['currency'] ?? null,
            channel: $data['channel'] ?? null,
            paidAt: ! empty($data['paid_at']) ? CarbonImmutable::parse($data['paid_at']) : null,
            message: $data['gateway_response'] ?? null,
            payload: array_intersect_key($data, array_flip([
                'id', 'status', 'reference', 'amount', 'currency', 'channel', 'gateway_response',
                'paid_at', 'created_at', 'fees', 'ip_address',
            ])),
        );
    }

    public function webhookReference(Request $request): ?string
    {
        $signature = (string) $request->header('x-paystack-signature');
        $expected = hash_hmac('sha512', $request->getContent(), $this->secretKey);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            throw new InvalidWebhookSignature('Paystack webhook signature does not match.');
        }

        if ($request->json('event') !== 'charge.success') {
            return null;
        }

        $reference = $request->json('data.reference');

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            return $call(Http::baseUrl($this->baseUrl)
                ->withToken($this->secretKey)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(20));
        } catch (ConnectionException $e) {
            throw new PaymentGatewayException('Could not reach Paystack: '.$e->getMessage(), previous: $e);
        }
    }
}
