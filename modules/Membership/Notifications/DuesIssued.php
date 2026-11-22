<?php

namespace Modules\Membership\Notifications;

use App\Models\School;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Membership\Models\DuesCharge;
use Modules\Membership\Notifications\Concerns\SmsAndMail;

/**
 * Sent to proprietors when dues or a levy are issued to their school.
 */
class DuesIssued extends Notification implements ShouldQueue
{
    use Queueable, SmsAndMail;

    public function __construct(
        public readonly DuesCharge $charge,
        public readonly School $school,
    ) {}

    public function toSms(object $notifiable): string
    {
        return config('napps.name').': '.$this->message().' '.__('Pay online:').' '.$this->url();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':title for :school', ['title' => $this->charge->title, 'school' => $this->school->name]))
            ->line($this->message())
            ->when($this->charge->description, fn (MailMessage $mail) => $mail->line((string) $this->charge->description))
            ->action(__('Pay online'), $this->url());
    }

    private function message(): string
    {
        return __(':title of :amount for :school is due by :date.', [
            'title' => $this->charge->title,
            'amount' => Money::format($this->charge->amount_kobo),
            'school' => $this->school->name,
            'date' => $this->charge->due_date->format('j M Y'),
        ]);
    }

    private function url(): string
    {
        return route('membership.dues.index', ['school' => $this->school->getKey()]);
    }
}
