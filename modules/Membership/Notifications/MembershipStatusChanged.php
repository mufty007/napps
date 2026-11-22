<?php

namespace Modules\Membership\Notifications;

use App\Models\School;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Membership\Notifications\Concerns\SmsAndMail;

/**
 * Suspension and reinstatement notices to the school's proprietors.
 */
class MembershipStatusChanged extends Notification
{
    use SmsAndMail;

    public function __construct(
        public readonly School $school,
        public readonly bool $suspended,
        public readonly ?string $reason = null,
    ) {}

    public function toSms(object $notifiable): string
    {
        return config('napps.name').': '.$this->message();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->suspended
                ? __('NAPPS membership suspended: :school', ['school' => $this->school->name])
                : __('NAPPS membership reinstated: :school', ['school' => $this->school->name]))
            ->line($this->message())
            ->action(__('Open NAPPS'), route('membership.dues.index', ['school' => $this->school->getKey()]));
    }

    private function message(): string
    {
        return $this->suspended
            ? __(':school\'s membership has been suspended (:reason). Contact your chapter to resolve it.', ['school' => $this->school->name, 'reason' => $this->reason])
            : __(':school\'s membership has been reinstated.', ['school' => $this->school->name]);
    }
}
