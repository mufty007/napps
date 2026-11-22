<?php

namespace App\Jobs;

use App\Models\GatewayTransaction;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-verify a transaction after a gateway webhook. Retried with backoff so a
 * short gateway or network outage never loses a payment.
 */
class VerifyGatewayTransaction implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public int $uniqueFor = 120;

    public function __construct(public GatewayTransaction $transaction) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300, 900];
    }

    public function uniqueId(): string
    {
        return (string) $this->transaction->getKey();
    }

    public function handle(PaymentService $payments): void
    {
        $payments->refresh($this->transaction);
    }
}
