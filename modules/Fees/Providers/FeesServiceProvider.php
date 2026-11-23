<?php

namespace Modules\Fees\Providers;

use App\Enums\Role;
use App\Events\PaymentConfirmed;
use App\Support\Dashboard\DashboardWidgets;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Modules\Fees\Console\SendFeeRemindersCommand;
use Modules\Fees\Listeners\RecordFeePayment;
use Modules\Fees\Models\FeeAgreement;
use Modules\Fees\Models\FeeInvoice;
use Modules\Fees\Models\FeeStructure;
use Modules\Fees\Policies\FeeAgreementPolicy;
use Modules\Fees\Policies\FeeInvoicePolicy;
use Modules\Fees\Policies\FeeStructurePolicy;
use Modules\Fees\Support\ArrearsWidget;
use Modules\Fees\Support\ParentFeesWidget;

/**
 * Fee structures, bulk term invoicing, Paystack payments and virtual accounts, bursar cash entry, reminders, arrears ageing, instalments, parent portal, fee agreements. Stories: FEE-1..4.
 */
class FeesServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Fees';

    protected function navigation(Navigation $navigation): void
    {
        $navigation
            ->add(new NavItem(
                key: 'fees.invoices',
                label: 'Fees',
                route: 'fees.invoices.index',
                icon: 'wallet',
                roles: [Role::Proprietor, Role::Bursar],
                order: 10,
                active: 'fees.invoices.*|fees.structures.*|fees.agreements.*',
                permission: 'fees.invoices.manage',
            ))
            ->add(new NavItem(
                key: 'fees.mine',
                label: 'My fees',
                route: 'fees.parent.index',
                icon: 'wallet',
                roles: [Role::Parent],
                order: 10,
                active: 'fees.parent.*|fees.agreements.*',
                permission: 'fees.pay',
            ));
    }

    protected function bootModule(): void
    {
        Gate::policy(FeeInvoice::class, FeeInvoicePolicy::class);
        Gate::policy(FeeStructure::class, FeeStructurePolicy::class);
        Gate::policy(FeeAgreement::class, FeeAgreementPolicy::class);

        // Parents are personal-scope, so the school scope would hide their own
        // invoices and agreements: bind by ownership and let the policies decide.
        Route::bind('parentInvoice', fn (string $id) => FeeInvoice::withoutGlobalScopes()
            ->where('guardian_user_id', auth()->id())
            ->whereNotIn('status', ['draft', 'void'])
            ->findOrFail((int) $id));

        Route::bind('agreement', fn (string $id) => FeeAgreement::withoutGlobalScopes()->findOrFail((int) $id));

        Event::listen(PaymentConfirmed::class, RecordFeePayment::class);

        $widgets = $this->app->make(DashboardWidgets::class);
        $widgets->add('school', 'fees::widgets.arrears', order: 30, permission: 'fees.arrears.view');
        $widgets->add('parent', 'fees::widgets.parent', order: 10, permission: 'fees.pay');

        View::composer('fees::widgets.arrears', ArrearsWidget::class);
        View::composer('fees::widgets.parent', ParentFeesWidget::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SendFeeRemindersCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('fees:send-reminders')->dailyAt('07:30')->withoutOverlapping()->onOneServer();
        });
    }
}
