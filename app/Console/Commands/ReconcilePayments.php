<?php

namespace App\Console\Commands;

use App\Enums\GatewayStatus;
use App\Models\GatewayTransaction;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Sweep: re-verify recent attempts that never got a callback or webhook
 * (payer closed the tab, webhook lost), and close stale ones.
 */
#[Signature('payments:reconcile {--hours=72 : How far back to look for unfinished payments}')]
#[Description('Re-verify unfinished online payments with the gateway')]
class ReconcilePayments extends Command
{
    public function handle(PaymentService $payments): int
    {
        $since = now()->subHours((int) $this->option('hours'));
        $checked = $confirmed = 0;

        GatewayTransaction::query()
            ->whereIn('status', [GatewayStatus::Pending, GatewayStatus::Abandoned])
            ->where('created_at', '>=', $since)
            ->where('created_at', '<=', now()->subMinutes(2))
            ->lazyById(100)
            ->each(function (GatewayTransaction $transaction) use ($payments, &$checked, &$confirmed) {
                $checked++;

                try {
                    if ($payments->refresh($transaction)->status === GatewayStatus::Success) {
                        $confirmed++;
                    }
                } catch (PaymentGatewayException $e) {
                    report($e);
                }
            });

        $closed = GatewayTransaction::query()
            ->where('status', GatewayStatus::Pending)
            ->where('created_at', '<', $since)
            ->update(['status' => GatewayStatus::Abandoned, 'updated_at' => now()]);

        $this->info("Checked {$checked}, confirmed {$confirmed}, closed {$closed} stale.");

        return self::SUCCESS;
    }
}
