<?php

namespace Modules\Templates\Providers;

use App\Support\Modules\ModuleServiceProvider;

/**
 * Versioned template library with legally-reviewed flag, fill-in fields, PDF export, starter set.
 */
class TemplatesServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Templates';
}
