<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\SmsGateway;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaystackGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\TermiiSmsGateway;
use App\Support\Dashboard\DashboardWidgets;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Scope\ActiveContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ActiveContext::class);

        $this->app->singleton(Navigation::class);

        $this->app->singleton(SmsGateway::class, fn () => match (config('napps.sms.driver')) {
            'termii' => new TermiiSmsGateway(
                (string) config('napps.sms.termii.api_key'),
                (string) config('napps.sms.termii.sender_id'),
                (string) config('napps.sms.termii.base_url'),
            ),
            default => new LogSmsGateway,
        });

        $this->app->singleton(DashboardWidgets::class);
        $this->app->singleton(NotificationPreferences::class);

        $this->app->singleton(PaymentGateway::class, function ($app) {
            $driver = config('napps.payments.driver');

            return match ($driver) {
                'paystack' => new PaystackGateway(
                    (string) config('napps.payments.paystack.secret_key'),
                    (string) config('napps.payments.paystack.base_url'),
                ),
                'fake' => $app->isProduction()
                    ? throw new RuntimeException('The fake payment gateway cannot be used in production. Set PAYMENTS_DRIVER=paystack.')
                    : new FakeGateway($app['cache']->store(), (string) config('app.key')),
                default => throw new InvalidArgumentException("Unknown payments driver [{$driver}]."),
            };
        });
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->uncompromised()
            : Password::min(8));

        Notification::extend('sms', fn ($app) => $app->make(SmsChannel::class));

        $this->registerAuthorization();
        $this->registerNavigation();

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        View::composer('components.layouts.app', function ($view): void {
            $context = $this->app->make(ActiveContext::class);
            $view->with('context', $context);
            $view->with('navItems', $this->app->make(Navigation::class)->for($context->role(), $context));
            $view->with('unreadNotifications', $context->user()?->unreadNotifications()->count() ?? 0);
        });
    }

    /**
     * Dotted abilities ("fees.invoices.manage") are permissions of the active
     * role. Anything else falls through to policies and plain gates.
     */
    private function registerAuthorization(): void
    {
        Gate::before(function (User $user, string $ability) {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return $this->app->make(ActiveContext::class)->allows($ability) ? true : null;
        });
    }

    private function registerNavigation(): void
    {
        $this->app->make(Navigation::class)->add(new NavItem(
            key: 'dashboard',
            label: 'Dashboard',
            route: 'dashboard',
            icon: 'layout-dashboard',
            roles: Role::cases(),
            order: 5,
            active: 'dashboard',
        ));
    }
}
