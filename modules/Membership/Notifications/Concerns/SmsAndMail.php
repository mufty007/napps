<?php

namespace Modules\Membership\Notifications\Concerns;

/**
 * Official membership notices: always in-app and by SMS (PRD: urgent official
 * notices always reach users by SMS), plus email when the person has one.
 */
trait SmsAndMail
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['database', 'sms', 'mail'] : ['database', 'sms'];
    }
}
