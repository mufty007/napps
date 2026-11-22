<?php

namespace Modules\Membership\Notifications;

use App\Models\School;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Membership\Models\DuesPayment;
use Modules\Membership\Notifications\Concerns\SmsAndMail;
use Modules\Membership\Services\MembershipDocuments;

/**
 * MEM-3: "a receipt is issued".
 */
class DuesPaymentReceived extends Notification
{
    use SmsAndMail;

    public bool $transactional = true;

    public function __construct(
        public readonly DuesPayment $payment,
        public readonly School $school,
    ) {}

    public function toSms(object $notifiable): string
    {
        return config('napps.name').': '.$this->message().' '.$this->url();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Receipt :number: NAPPS dues for :school', ['number' => $this->payment->receipt_number, 'school' => $this->school->name]))
            ->line($this->message())
            ->action(__('Download receipt'), $this->url())
            ->attachData(
                app(MembershipDocuments::class)->receipt($this->payment)->output(),
                $this->payment->receipt_number.'.pdf',
                ['mime' => 'application/pdf'],
            );
    }

    private function message(): string
    {
        return __('We received :amount for :school. Receipt :number:', [
            'amount' => Money::format($this->payment->amount_kobo),
            'school' => $this->school->name,
            'number' => $this->payment->receipt_number,
        ]);
    }

    private function url(): string
    {
        return app(MembershipDocuments::class)->receiptUrl($this->payment);
    }
}
