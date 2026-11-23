<?php

namespace Modules\Leases\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Leases\Models\Lease;
use Modules\Leases\Notifications\Concerns\LeaseNotice;

/**
 * Sent to a school's proprietors as its lease nears the end: once in each of
 * the 90, 60 and 30 day windows. `$window` is the window (90, 60 or 30) and
 * `$days` the days actually left when it went out.
 */
class LeaseRenewalReminder extends Notification implements ShouldQueue
{
    use LeaseNotice, Queueable;

    public function __construct(
        public readonly Lease $lease,
        public readonly int $days,
        public readonly int $window,
    ) {}

    public function toSms(object $notifiable): string
    {
        return config('napps.name').': '.$this->message().' '.$this->url();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->message())
            ->line(__('Talk to your landlord about renewal early, and keep the signed renewal in your lease vault.'))
            ->action(__('Open the lease'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->message(),
            'url' => $this->url(),
            'icon' => 'building-2',
            'lease_id' => $this->lease->getKey(),
            'window' => $this->window,
            'days' => $this->days,
        ];
    }

    private function title(): string
    {
        return $this->days <= 0
            ? __('Your lease ends today')
            : trans_choice('Your lease ends in :count day|Your lease ends in :count days', $this->days);
    }

    private function message(): string
    {
        return __('The lease on :address (landlord: :landlord) ends on :date.', [
            'address' => str($this->lease->property_address)->squish()->limit(60)->toString(),
            'landlord' => $this->lease->landlord_name,
            'date' => $this->lease->end_date->format('j M Y'),
        ]);
    }

    private function url(): string
    {
        return route('leases.show', $this->lease);
    }
}
