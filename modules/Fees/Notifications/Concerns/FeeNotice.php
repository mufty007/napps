<?php

namespace Modules\Fees\Notifications\Concerns;

use App\Models\User;

/**
 * Fee notices go to the guardian by SMS (urgent official notices always reach
 * people by SMS) and, when the guardian has an account, in the app too. A
 * guardian who is only a phone number on the invoice gets the SMS alone.
 */
trait FeeNotice
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['database', 'sms'] : ['sms'];
    }
}
