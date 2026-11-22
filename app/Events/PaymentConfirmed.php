<?php

namespace App\Events;

use App\Models\GatewayTransaction;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A gateway transaction has just been verified as paid. Fired exactly once
 * per transaction, inside the database transaction that marks it paid:
 * listeners run synchronously, and if one throws, the confirmation rolls
 * back and is retried by the webhook job or the reconciliation sweep.
 *
 * Listeners check $transaction->purpose and ignore other modules' payments.
 */
class PaymentConfirmed
{
    use Dispatchable;

    public function __construct(public readonly GatewayTransaction $transaction) {}
}
