<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registers every enabled domain module's service provider.
 */
class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (config('modules.enabled', []) as $module) {
            $provider = "Modules\\{$module}\\Providers\\{$module}ServiceProvider";

            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }
}
