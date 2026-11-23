<?php

namespace Modules\Fees\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Fees\Services\FeeInvoiceService;

/**
 * Nightly: ages issued invoices into Overdue, then texts families whose
 * invoices fall due soon, are due today, or are 7, 14 or 30 days late.
 * Each reminder kind goes out once per invoice, and families who did not
 * consent to reminders are skipped.
 */
class SendFeeReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @return array{overdue: int, sent: int}
     */
    public function handle(FeeInvoiceService $invoices): array
    {
        $result = $invoices->sendScheduledReminders();

        Log::info('Fee reminders run', $result);

        return $result;
    }
}
