<?php

namespace App\Notifications\Channels;

use App\Contracts\SmsGateway;
use Illuminate\Notifications\Notification;

/**
 * Notification channel: add 'sms' (this class) to via() and implement
 * toSms($notifiable): string on the notification.
 */
class SmsChannel
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (! $to || ! method_exists($notification, 'toSms')) {
            return;
        }

        $transactional = property_exists($notification, 'transactional') && $notification->transactional;

        $this->gateway->send($to, $notification->toSms($notifiable), $transactional);
    }
}
