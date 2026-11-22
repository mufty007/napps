<?php

namespace Modules\Membership\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Membership\Services\ComplianceService;

/**
 * Nightly: schools whose dues fell due become Owing, automatic suspensions
 * are applied or lifted (if configured), and paid-up schools become Active.
 */
#[Signature('membership:refresh-compliance')]
#[Description('Recompute Active / Owing / Suspended for every member school')]
class RefreshCompliance extends Command
{
    public function handle(ComplianceService $compliance): int
    {
        $changed = $compliance->refresh();

        $this->info("{$changed} school(s) changed status.");

        return self::SUCCESS;
    }
}
