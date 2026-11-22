<?php

namespace Modules\Claims\Providers;

use App\Support\Modules\ModuleServiceProvider;

/**
 * Escalate 90+ day debts to claim packs, case pipeline (demand -> enforcement), chapter mediation, legal panel, per-state enablement. Stories: CLM-1..2.
 */
class ClaimsServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Claims';
}
