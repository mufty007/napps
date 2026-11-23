<?php

namespace Modules\Community\Notifications\Concerns;

use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Community notifications are always in-app; SMS and email only when the
 * person switched them on for this category (PRD: per-type channel choice).
 * Use with a CATEGORY constant on the notification.
 */
trait UsesPreferences
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User
            ? app(NotificationPreferences::class)->channelsFor($notifiable, static::CATEGORY)
            : ['database'];
    }

    public function toSms(object $notifiable): string
    {
        $data = $this->toArray($notifiable);

        return config('napps.name').': '.$data['title'].'. '.$data['url'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->line($data['body'] ?? '')
            ->action(__('Open'), $data['url']);
    }
}
