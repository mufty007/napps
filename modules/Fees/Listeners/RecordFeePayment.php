<?php

namespace Modules\Fees\Listeners;

use App\Events\PaymentConfirmed;
use Modules\Fees\Enums\FeePaymentMethod;
use Modules\Fees\Models\FeeInvoice;
use Modules\Fees\Services\FeeInvoiceService;

/**
 * FEE-2: a verified online payment is recorded against the invoice it was
 * started for. Runs inside the kernel's confirmation transaction, so a
 * failure leaves the payment to be retried; recording is idempotent per
 * gateway transaction.
 */
class RecordFeePayment
{
    public function __construct(private readonly FeeInvoiceService $invoices) {}

    public function handle(PaymentConfirmed $event): void
    {
        $transaction = $event->transaction;

        if ($transaction->purpose !== FeeInvoiceService::PURPOSE) {
            return;
        }

        $invoice = FeeInvoice::withoutGlobalScopes()
            ->where('school_id', $transaction->school_id)
            ->findOrFail((int) $transaction->meta('invoice_id'));

        $this->invoices->recordPayment(
            invoice: $invoice,
            amountKobo: $transaction->amount_kobo,
            method: FeePaymentMethod::Paystack,
            recordedBy: null,
            transaction: $transaction,
            paidAt: $transaction->paid_at ?? now(),
            reference: $transaction->reference,
            meta: array_filter(['channel' => $transaction->channel, 'payer_id' => $transaction->payer_id]),
        );
    }
}
