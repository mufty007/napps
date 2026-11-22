<?php

namespace Modules\Membership\Listeners;

use App\Events\PaymentConfirmed;
use App\Models\School;
use Carbon\CarbonImmutable;
use Modules\Membership\Enums\PaymentMethod;
use Modules\Membership\Models\MembershipDue;
use Modules\Membership\Services\DuesLedger;

/**
 * MEM-3: a verified online payment marks the school's dues paid, issues the
 * receipt and makes the school Active. Runs inside the kernel's confirmation
 * transaction, so a failure here leaves the payment to be retried.
 */
class RecordOnlineDuesPayment
{
    public function __construct(private readonly DuesLedger $ledger) {}

    public function handle(PaymentConfirmed $event): void
    {
        $transaction = $event->transaction;

        if ($transaction->purpose !== DuesLedger::PURPOSE) {
            return;
        }

        $school = School::withoutGlobalScopes()->withTrashed()->findOrFail($transaction->school_id);

        $dues = MembershipDue::withoutGlobalScopes()
            ->whereKey((array) $transaction->meta('due_ids', []))
            ->where('school_id', $school->getKey())
            ->get();

        $this->ledger->recordPayment(
            school: $school,
            dues: $dues,
            method: PaymentMethod::Online,
            amountKobo: $transaction->amount_kobo,
            paidAt: CarbonImmutable::parse($transaction->paid_at ?? now()),
            reference: $transaction->reference,
            transaction: $transaction,
            paidBy: $transaction->payer,
        );
    }
}
