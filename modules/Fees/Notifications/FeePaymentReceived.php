<?php

namespace Modules\Fees\Notifications;

use App\Support\Money;
use Illuminate\Notifications\Notification;
use Modules\Fees\Models\FeeInvoice;
use Modules\Fees\Models\FeePayment;
use Modules\Fees\Notifications\Concerns\FeeNotice;

/**
 * Sent to the guardian when a payment is recorded against their invoice.
 */
class FeePaymentReceived extends Notification
{
    use FeeNotice;

    public bool $transactional = true;

    public function __construct(
        public readonly FeeInvoice $invoice,
        public readonly FeePayment $payment,
        public readonly string $schoolName,
    ) {}

    public function toSms(object $notifiable): string
    {
        return config('napps.name').': '.$this->message();
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => __('Payment received'), 'body' => $this->message(), 'url' => route('fees.parent.show', $this->invoice), 'icon' => 'circle-check'];
    }

    private function message(): string
    {
        $balance = $this->invoice->balanceKobo();

        return __(':school received :amount for :student.', [
            'school' => $this->schoolName,
            'amount' => Money::format($this->payment->amount_kobo),
            'student' => $this->invoice->student_name,
        ]).' '.($balance > 0
            ? __('Balance: :amount.', ['amount' => Money::format($balance)])
            : __('Fully paid. Thank you!'));
    }
}
