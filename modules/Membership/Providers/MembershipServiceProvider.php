<?php

namespace Modules\Membership\Providers;

use App\Enums\Role;
use App\Events\PaymentConfirmed;
use App\Support\Dashboard\DashboardWidgets;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Modules\Membership\Console\RefreshCompliance;
use Modules\Membership\Http\Composers\ApplicantWidget;
use Modules\Membership\Http\Composers\ExecutiveWidget;
use Modules\Membership\Http\Composers\SchoolWidget;
use Modules\Membership\Listeners\RecordOnlineDuesPayment;
use Modules\Membership\Models\MembershipApplication;

/**
 * Member registry, onboarding (apply -> chapter verify -> state approve),
 * digital certificate with QR, dues and split ledger, compliance status,
 * public verify page. Stories: MEM-1..3.
 */
class MembershipServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Membership';

    private const EXECUTIVES = [Role::Secretariat, Role::NationalExecutive, Role::StateExecutive, Role::ChapterExecutive];

    protected function navigation(Navigation $navigation): void
    {
        $navigation
            ->add(new NavItem(
                key: 'membership.schools',
                label: 'Schools',
                route: 'membership.schools.index',
                icon: 'school',
                roles: self::EXECUTIVES,
                order: 20,
                active: 'membership.schools.*',
                permission: 'org.view',
            ))
            // PRD screen names: "Verify new school" (chapter), "Member approvals" (state).
            ->add(new NavItem(
                key: 'membership.verify',
                label: 'Verify schools',
                route: 'membership.applications.index',
                icon: 'clipboard-check',
                roles: [Role::ChapterExecutive],
                order: 30,
                active: 'membership.applications.*',
                permission: 'membership.applications.verify',
            ))
            ->add(new NavItem(
                key: 'membership.approvals',
                label: 'Approvals',
                route: 'membership.applications.index',
                icon: 'clipboard-check',
                roles: [Role::StateExecutive, Role::Secretariat],
                order: 30,
                active: 'membership.applications.*',
                permission: 'membership.applications.approve',
            ))
            ->add(new NavItem(
                key: 'membership.dues',
                label: 'Dues',
                route: 'membership.charges.index',
                icon: 'hand-coins',
                roles: self::EXECUTIVES,
                order: 40,
                active: 'membership.charges.*',
                permission: 'membership.dues.configure',
            ))
            ->add(new NavItem(
                key: 'membership.parents',
                label: 'Parents',
                route: 'membership.parents.index',
                icon: 'users',
                roles: [Role::ChapterExecutive],
                order: 35,
                active: 'membership.parents.*',
                permission: 'parents.manage',
            ));
    }

    protected function bootModule(): void
    {
        // Applicants (no role yet) reach only their own applications.
        Route::bind('myApplication', fn (string $id) => MembershipApplication::withoutGlobalScopes()
            ->where('applicant_id', auth()->id())
            ->findOrFail((int) $id));

        Event::listen(PaymentConfirmed::class, RecordOnlineDuesPayment::class);

        $widgets = $this->app->make(DashboardWidgets::class);
        $widgets->add(['none', 'school'], 'membership::widgets.applications', order: 10);
        $widgets->add('school', 'membership::widgets.school', order: 20);
        $widgets->add(['secretariat', 'executive'], 'membership::widgets.executive', order: 20, permission: 'org.view');

        View::composer('membership::widgets.applications', ApplicantWidget::class);
        View::composer('membership::widgets.school', SchoolWidget::class);
        View::composer('membership::widgets.executive', ExecutiveWidget::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RefreshCompliance::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('membership:refresh-compliance')->dailyAt('00:30')->withoutOverlapping()->onOneServer();
        });
    }
}
