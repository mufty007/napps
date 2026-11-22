<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\GatewayStatus;
use App\Models\GatewayTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Local stand-in for Paystack: "checkout" is a page in this app where you
 * choose the outcome. Refused in production (see AppServiceProvider).
 */
class FakeGateway implements PaymentGateway
{
    public const WEBHOOK_HEADER = 'x-fake-signature';

    public function __construct(
        private readonly Cache $cache,
        private readonly string $secret,
    ) {}

    public function name(): string
    {
        return 'fake';
    }

    public function initialize(GatewayTransaction $transaction, string $email, string $callbackUrl): string
    {
        return URL::route('payments.fake.show', $transaction->reference);
    }

    public function verify(GatewayTransaction $transaction): GatewayResult
    {
        $status = GatewayStatus::tryFrom((string) $this->cache->get($this->key($transaction->reference)))
            ?? GatewayStatus::Pending;

        return new GatewayResult(
            status: $status,
            amountKobo: $transaction->amount_kobo,
            currency: $transaction->currency,
            channel: $status === GatewayStatus::Success ? 'card' : null,
            paidAt: $status === GatewayStatus::Success ? CarbonImmutable::now() : null,
            message: $status === GatewayStatus::Success ? 'Approved (simulated)' : null,
            payload: ['simulated' => true, 'status' => $status->value],
        );
    }

    public function webhookReference(Request $request): ?string
    {
        if (! hash_equals($this->sign($request->getContent()), (string) $request->header(self::WEBHOOK_HEADER))) {
            throw new InvalidWebhookSignature('Fake webhook signature does not match.');
        }

        return $request->json('event') === 'charge.success' ? $request->json('data.reference') : null;
    }

    /**
     * Decide what the "payer" did at checkout.
     */
    public function setOutcome(string $reference, GatewayStatus $status): void
    {
        $this->cache->put($this->key($reference), $status->value, now()->addDay());
    }

    public function sign(string $payload): string
    {
        return hash_hmac('sha512', $payload, $this->secret);
    }

    private function key(string $reference): string
    {
        return 'payments:fake:'.$reference;
    }
}
