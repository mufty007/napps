<?php

namespace Modules\Surveys\Providers;

use App\Support\Modules\ModuleServiceProvider;

/**
 * Survey builder, targeting, reminders, results by chapter/state, CSV export, NAPPS needs assessment. Story: SRV-1.
 */
class SurveysServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Surveys';
}
