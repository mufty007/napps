<?php

namespace Modules\Membership\Notifications\Concerns;

/**
 * Everyone has a phone; email is optional on NAPPS accounts.
 */
trait SmsAndMail
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['sms', 'mail'] : ['sms'];
    }
}
