<?php

namespace Modules\Leases\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Leases\Models\LeaseDispute;
use Modules\Leases\Notifications\Concerns\LeaseNotice;

/** Tells the people involved that a lease dispute was opened, moved or resolved. */
class LeaseDisputeUpdated extends Notification implements ShouldQueue
{
    use LeaseNotice, Queueable;

    /** @param  'opened'|'chapter_help'|'legal'|'resolved'  $event */
    public function __construct(public readonly LeaseDispute $dispute, public readonly string $event) {}

    public function toSms(object $notifiable): string
    {
        return config('napps.name').': '.$this->message().' '.$this->url();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->message())
            ->action(__('Open the dispute'), $this->url());
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->message(),
            'url' => $this->url(),
            'icon' => 'scale',
        ];
    }

    private function title(): string
    {
        return match ($this->event) {
            'opened' => __('Lease dispute opened'),
            'chapter_help' => __('Chapter help requested on a lease dispute'),
            'legal' => __('Lease dispute passed to the legal panel'),
            default => __('Lease dispute resolved'),
        };
    }

    private function message(): string
    {
        $school = $this->dispute->school->name ?? __('A school');
        $address = str($this->dispute->lease->property_address ?? '')->squish()->limit(50)->toString();

        return match ($this->event) {
            'opened' => __(':school opened a dispute with its landlord over :address.', ['school' => $school, 'address' => $address]),
            'chapter_help' => __(':school asked the chapter to help with its landlord dispute over :address.', ['school' => $school, 'address' => $address]),
            'legal' => __('The dispute between :school and its landlord over :address was passed to the legal panel.', ['school' => $school, 'address' => $address]),
            default => __('The dispute between :school and its landlord over :address was resolved.', ['school' => $school, 'address' => $address]),
        };
    }

    private function url(): string
    {
        return route('leases.disputes.show', $this->dispute);
    }
}
