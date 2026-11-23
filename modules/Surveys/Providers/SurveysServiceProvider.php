<?php

namespace Modules\Surveys\Providers;

use App\Enums\Role;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;
use Illuminate\Support\Facades\Gate;
use Modules\Surveys\Models\Survey;
use Modules\Surveys\Policies\SurveyPolicy;

/**
 * Survey builder, targeting, results by chapter/state, CSV export, NAPPS needs assessment. Story: SRV-1.
 */
class SurveysServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Surveys';

    private const EXECUTIVES = [Role::Secretariat, Role::NationalExecutive, Role::StateExecutive, Role::ChapterExecutive];

    protected function navigation(Navigation $navigation): void
    {
        $navigation
            ->add(new NavItem(
                key: 'surveys.manage',
                label: 'Surveys',
                route: 'surveys.index',
                icon: 'clipboard-list',
                roles: self::EXECUTIVES,
                order: 58,
                active: 'surveys.index|surveys.create|surveys.edit|surveys.results*',
                permission: 'surveys.issue',
                mobile: false,
            ))
            ->add(new NavItem(
                key: 'surveys.respond',
                label: 'Surveys',
                route: 'surveys.respond.index',
                icon: 'clipboard-list',
                roles: [Role::Proprietor],
                order: 58,
                active: 'surveys.respond.*',
                permission: 'surveys.respond',
                mobile: false,
            ));
    }

    protected function bootModule(): void
    {
        Gate::policy(Survey::class, SurveyPolicy::class);
    }
}
