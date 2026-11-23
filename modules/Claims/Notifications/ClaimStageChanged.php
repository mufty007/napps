<?php

namespace Modules\Claims\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Claims\Enums\ClaimStage;
use Modules\Claims\Models\ClaimCase;
use Modules\Claims\Notifications\Concerns\ClaimNotice;

/** Sent to the school that opened a claim (and its lawyer) when the stage moves. */
class ClaimStageChanged extends Notification implements ShouldQueue
{
    use ClaimNotice, Queueable;

    public function __construct(
        public readonly ClaimCase $case,
        public readonly ClaimStage $from,
        public readonly ClaimStage $to,
    ) {}

    public function toSms(object $notifiable): string
    {
        return config('napps.name').': '.$this->message().' '.$this->url();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Claim :ref: :stage', ['ref' => $this->case->reference, 'stage' => $this->to->label()]))
            ->line($this->message())
            ->action(__('View the claim'), $this->url());
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Claim :ref: :stage', ['ref' => $this->case->reference, 'stage' => $this->to->label()]),
            'body' => $this->message(),
            'url' => $this->url(),
            'icon' => 'scale',
        ];
    }

    private function message(): string
    {
        return __('Claim :ref against :debtor moved from ":from" to ":to".', [
            'ref' => $this->case->reference,
            'debtor' => $this->case->debtor_name,
            'from' => $this->from->label(),
            'to' => $this->to->label(),
        ]);
    }

    private function url(): string
    {
        return route('claims.show', $this->case);
    }
}
