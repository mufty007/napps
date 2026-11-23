<?php

namespace Modules\Leases\Notifications\Concerns;

/**
 * Lease notices are always in-app and by SMS, plus email when the person has one.
 */
trait LeaseNotice
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['database', 'sms', 'mail'] : ['database', 'sms'];
    }
}
