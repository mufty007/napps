<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\GatewayStatus;
use App\Events\PaymentConfirmed;
use App\Models\GatewayTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * The only way modules take online payments.
 *
 *   $transaction = $payments->start('membership.dues', $kobo, $user, $schoolId, $returnUrl, [...]);
 *   return redirect()->away($transaction->authorization_url);
 *
 * Confirmation is idempotent: the callback, the webhook job and the
 * reconciliation sweep all call refresh(), and PaymentConfirmed fires once.
 */
class PaymentService
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * Record the attempt and open a checkout at the gateway.
     *
     * @param  array<string, mixed>  $metadata  Module data needed to apply the payment later.
     *
     * @throws PaymentGatewayException
     */
    public function start(
        string $purpose,
        int $amountKobo,
        User $payer,
        ?int $schoolId,
        string $returnUrl,
        array $metadata = [],
    ): GatewayTransaction {
        if ($amountKobo <= 0) {
            throw new PaymentGatewayException('Nothing to pay.');
        }

        $transaction = GatewayTransaction::query()->create([
            'reference' => 'NAPPS-'.Str::ulid(),
            'gateway' => $this->gateway->name(),
            'purpose' => $purpose,
            'school_id' => $schoolId,
            'payer_id' => $payer->getKey(),
            'amount_kobo' => $amountKobo,
            'currency' => config('napps.payments.currency', 'NGN'),
            'status' => GatewayStatus::Pending,
            'metadata' => [...$metadata, 'return_url' => $returnUrl],
        ]);

        try {
            $url = $this->gateway->initialize($transaction, $this->emailFor($payer), URL::route('payments.callback'));
        } catch (PaymentGatewayException $e) {
            $transaction->update(['status' => GatewayStatus::Failed, 'gateway_response' => Str::limit($e->getMessage(), 250)]);

            throw $e;
        }

        $transaction->update(['authorization_url' => $url]);

        return $transaction;
    }

    /**
     * Ask the gateway about the transaction and apply the answer once.
     *
     * @throws PaymentGatewayException when the gateway is unreachable
     */
    public function refresh(GatewayTransaction $transaction): GatewayTransaction
    {
        if ($transaction->status->isFinal()) {
            return $transaction;
        }

        $result = $this->gateway->verify($transaction);

        return DB::transaction(function () use ($transaction, $result) {
            /** @var GatewayTransaction $locked */
            $locked = GatewayTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());

            if ($locked->status->isFinal()) {
                return $locked;
            }

            $attributes = [
                'verified_at' => now(),
                'gateway_payload' => $result->payload ?: $locked->gateway_payload,
                'gateway_response' => $result->message !== null ? Str::limit($result->message, 250) : $locked->gateway_response,
                'channel' => $result->channel ?? $locked->channel,
            ];

            if ($result->status === GatewayStatus::Success) {
                if ($result->amountKobo !== $locked->amount_kobo || strtoupper((string) $result->currency) !== $locked->currency) {
                    // Never credit an amount we did not ask for; a person must look at it.
                    Log::critical('Gateway amount mismatch', [
                        'reference' => $locked->reference,
                        'expected' => [$locked->amount_kobo, $locked->currency],
                        'reported' => [$result->amountKobo, $result->currency],
                    ]);

                    $locked->update([...$attributes, 'status' => GatewayStatus::Failed, 'gateway_response' => 'Amount or currency mismatch: held for review']);

                    return $locked;
                }

                $locked->update([...$attributes, 'status' => GatewayStatus::Success, 'paid_at' => $result->paidAt ?? now()]);

                PaymentConfirmed::dispatch($locked);

                return $locked;
            }

            $locked->update([...$attributes, 'status' => $result->status]);

            return $locked;
        });
    }

    /**
     * Paystack needs an email; most members sign in by phone only, so they
     * get a stable no-reply address on our domain.
     */
    public function emailFor(User $user): string
    {
        if ($user->email) {
            return $user->email;
        }

        $digits = preg_replace('/\D+/', '', (string) $user->phone) ?: 'member'.$user->getKey();

        return $digits.'@'.config('napps.payments.fallback_email_domain');
    }
}
