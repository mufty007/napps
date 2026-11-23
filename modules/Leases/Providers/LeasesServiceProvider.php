<?php

namespace Modules\Leases\Providers;

use App\Enums\Role;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\Leases\Console\SendRenewalReminders;
use Modules\Leases\Models\Lease;
use Modules\Leases\Models\LeaseDispute;
use Modules\Leases\Policies\LeaseDisputePolicy;
use Modules\Leases\Policies\LeasePolicy;

/**
 * Lease register, rent log, renewal reminders (90/60/30), document vault, dispute log, model lease. Story: LSE-1.
 */
class LeasesServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Leases';

    protected function navigation(Navigation $navigation): void
    {
        $navigation
            ->add(new NavItem(
                key: 'leases',
                label: 'Leases',
                route: 'leases.index',
                icon: 'building-2',
                roles: [Role::Proprietor, Role::Secretariat],
                order: 40,
                active: 'leases.*',
                permission: 'leases.manage',
            ))
            // Executives and the panel see disputes, not the school's lease register.
            ->add(new NavItem(
                key: 'leases.disputes',
                label: 'Lease disputes',
                route: 'leases.disputes.index',
                icon: 'scale',
                roles: [Role::Secretariat, Role::StateExecutive, Role::ChapterExecutive, Role::LegalPanel],
                order: 46,
                active: 'leases.disputes.*',
                section: 'Association',
            ));
    }

    protected function bootModule(): void
    {
        Gate::policy(Lease::class, LeasePolicy::class);
        Gate::policy(LeaseDispute::class, LeaseDisputePolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SendRenewalReminders::class]);
        }

        // 90/60/30 day renewal reminders (and expiring finished leases), early each morning.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('leases:send-renewal-reminders')->dailyAt('06:30')->withoutOverlapping()->onOneServer();
        });
    }
}
