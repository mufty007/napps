<?php

namespace Modules\Claims\Notifications\Concerns;

/**
 * Claim updates are official notices: always in-app and by SMS, plus email
 * when the person has one.
 */
trait ClaimNotice
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['database', 'sms', 'mail'] : ['database', 'sms'];
    }
}
