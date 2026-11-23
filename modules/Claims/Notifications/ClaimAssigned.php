<?php

namespace Modules\Claims\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Claims\Models\ClaimCase;
use Modules\Claims\Notifications\Concerns\ClaimNotice;

/** Sent to a panel lawyer when a claim file is assigned to them. */
class ClaimAssigned extends Notification implements ShouldQueue
{
    use ClaimNotice, Queueable;

    public function __construct(public readonly ClaimCase $case) {}

    public function toSms(object $notifiable): string
    {
        return config('napps.name').': '.$this->message().' '.$this->url();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Claim :ref assigned to you', ['ref' => $this->case->reference]))
            ->line($this->message())
            ->action(__('Open the case file'), $this->url());
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Claim :ref assigned to you', ['ref' => $this->case->reference]),
            'body' => $this->message(),
            'url' => $this->url(),
            'icon' => 'scale',
        ];
    }

    private function message(): string
    {
        return __('You have been assigned claim :ref: :debtor owes :school :amount.', [
            'ref' => $this->case->reference,
            'debtor' => $this->case->debtor_name,
            'school' => $this->case->school?->name,
            'amount' => $this->case->amount(),
        ]);
    }

    private function url(): string
    {
        return route('claims.show', $this->case);
    }
}
