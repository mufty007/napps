<?php

namespace Modules\Records\Providers;

use App\Support\Modules\ModuleServiceProvider;

/**
 * Student/guardian/staff records, results and report cards, transfer clearance (5 working-day SLA), consent-gated debtor check, retention reports. Stories: REC-1..2.
 */
class RecordsServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Records';
}
