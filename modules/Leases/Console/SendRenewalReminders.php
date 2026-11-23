<?php

namespace Modules\Leases\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Leases\Services\LeaseService;

/**
 * Daily: mark leases past their end date as expired, then remind each school's
 * proprietors when a lease is within 90, 60 and 30 days of ending. Each window
 * is sent once per lease and person, so running it twice is harmless.
 */
#[Signature('leases:send-renewal-reminders')]
#[Description('Expire finished leases and send the 90/60/30 day renewal reminders')]
class SendRenewalReminders extends Command
{
    public function handle(LeaseService $leases): int
    {
        $expired = $leases->markExpired();
        $sent = $leases->sendRenewalReminders();

        $this->info("{$expired} lease(s) marked expired, {$sent} renewal reminder(s) sent.");

        return self::SUCCESS;
    }
}
