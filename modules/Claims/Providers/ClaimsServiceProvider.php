<?php

namespace Modules\Claims\Providers;

use App\Enums\Role;
use App\Support\Dashboard\DashboardWidgets;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Modules\Claims\Models\ClaimCase;
use Modules\Claims\Policies\ClaimCasePolicy;
use Modules\Claims\Support\ClaimsOverviewWidget;

/**
 * Escalate 90+ day debts to claim packs, case pipeline (demand -> enforcement),
 * chapter mediation, legal panel, per-state enablement. Stories: CLM-1..2.
 */
class ClaimsServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Claims';

    protected function navigation(Navigation $navigation): void
    {
        $navigation->add(new NavItem(
            key: 'claims',
            label: 'Claims',
            route: 'claims.index',
            icon: 'scale',
            roles: [
                Role::Secretariat,
                Role::NationalExecutive,
                Role::StateExecutive,
                Role::ChapterExecutive,
                Role::Proprietor,
                Role::LegalPanel,
            ],
            order: 45,
            active: 'claims.*',
        ));
    }

    protected function bootModule(): void
    {
        Gate::policy(ClaimCase::class, ClaimCasePolicy::class);

        $this->app->make(DashboardWidgets::class)
            ->add(['secretariat', 'executive'], 'claims::widgets.overview', order: 40, permission: 'claims.escalated.view');

        View::composer('claims::widgets.overview', ClaimsOverviewWidget::class);
    }
}
