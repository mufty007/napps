<?php

namespace App\Support\Modules;

use App\Support\Navigation\Navigation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base provider for a domain module in modules/{Name}.
 *
 * Conventions (all optional, loaded when present):
 *   routes/web.php          - wrapped in the "web" middleware group
 *   resources/views         - view namespace is the lower-case module name ("fees::")
 *   database/migrations
 *   lang                    - JSON translations
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Module directory name, e.g. "Fees".
     */
    protected string $module;

    public function boot(): void
    {
        $namespace = $this->viewNamespace();

        if (is_dir($views = $this->modulePath('resources/views'))) {
            $this->loadViewsFrom($views, $namespace);
        }

        if (is_dir($migrations = $this->modulePath('database/migrations'))) {
            $this->loadMigrationsFrom($migrations);
        }

        if (is_dir($lang = $this->modulePath('lang'))) {
            $this->loadJsonTranslationsFrom($lang);
        }

        if (is_file($routes = $this->modulePath('routes/web.php')) && ! $this->app->routesAreCached()) {
            Route::middleware('web')->group($routes);
        }

        $this->navigation($this->app->make(Navigation::class));

        $this->bootModule();
    }

    /**
     * Register this module's navigation entries.
     */
    protected function navigation(Navigation $navigation): void {}

    /**
     * Module-specific boot logic (policies, listeners, schedules).
     */
    protected function bootModule(): void {}

    protected function viewNamespace(): string
    {
        return strtolower($this->module);
    }

    protected function modulePath(string $path = ''): string
    {
        return rtrim(config('modules.path').DIRECTORY_SEPARATOR.$this->module.DIRECTORY_SEPARATOR.$path, DIRECTORY_SEPARATOR);
    }
}
