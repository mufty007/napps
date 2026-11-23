<?php

namespace Modules\Fees\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Fees\Jobs\SendFeeReminders;
use Modules\Fees\Services\FeeInvoiceService;

/**
 * Scheduled daily. Queues the reminder run; --now does it here and prints the result.
 */
#[Signature('fees:send-reminders {--now : Run immediately instead of queueing}')]
#[Description('Mark overdue invoices and send due fee reminders')]
class SendFeeRemindersCommand extends Command
{
    public function handle(FeeInvoiceService $invoices): int
    {
        if (! $this->option('now')) {
            SendFeeReminders::dispatch();
            $this->info('Fee reminders queued.');

            return self::SUCCESS;
        }

        $result = $invoices->sendScheduledReminders();

        $this->info("{$result['overdue']} invoice(s) marked overdue, {$result['sent']} reminder(s) sent.");

        return self::SUCCESS;
    }
}
