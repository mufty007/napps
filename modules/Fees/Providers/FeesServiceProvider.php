<?php

namespace Modules\Fees\Providers;

use App\Support\Modules\ModuleServiceProvider;

/**
 * Fee structures, bulk term invoicing, Paystack payments and virtual accounts, bursar cash entry, reminders, arrears ageing, instalments, parent portal, fee agreements. Stories: FEE-1..4.
 */
class FeesServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Fees';
}
