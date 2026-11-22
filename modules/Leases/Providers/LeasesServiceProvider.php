<?php

namespace Modules\Leases\Providers;

use App\Support\Modules\ModuleServiceProvider;

/**
 * Lease register, rent log, renewal reminders (90/60/30), document vault, dispute log, model lease. Story: LSE-1.
 */
class LeasesServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Leases';
}
