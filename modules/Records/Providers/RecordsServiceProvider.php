<?php

namespace Modules\Records\Providers;

use App\Contracts\StudentFeeBalances;
use App\Enums\Role;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;
use Illuminate\Support\Facades\Gate;
use Modules\Records\Models\StaffMember;
use Modules\Records\Models\Student;
use Modules\Records\Models\TransferRequest;
use Modules\Records\Policies\StaffMemberPolicy;
use Modules\Records\Policies\StudentPolicy;
use Modules\Records\Policies\TransferRequestPolicy;
use Modules\Records\Support\RecordedBalances;

/**
 * Student/guardian/staff records, results and report cards, transfer clearance (5 working-day SLA), consent-gated debtor check, retention reports. Stories: REC-1..2.
 */
class RecordsServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Records';

    public function register(): void
    {
        // Fees (registered earlier) may already have bound invoice-based balances.
        $this->app->bindIf(StudentFeeBalances::class, RecordedBalances::class);
    }

    protected function navigation(Navigation $navigation): void
    {
        $navigation
            ->add(new NavItem(
                key: 'records.students',
                label: 'Students',
                route: 'records.students.index',
                icon: 'graduation-cap',
                roles: [Role::Proprietor, Role::Bursar, Role::Staff],
                order: 30,
                active: 'records.students.*',
                permission: 'records.students.manage',
            ))
            ->add(new NavItem(
                key: 'records.staff',
                label: 'Staff records',
                route: 'records.staff.index',
                icon: 'users',
                roles: [Role::Proprietor],
                order: 31,
                active: 'records.staff.*',
                mobile: false,
                permission: 'records.staff.manage',
            ))
            ->add(new NavItem(
                key: 'records.transfers',
                label: 'Transfers',
                route: 'records.transfers.index',
                icon: 'arrow-left-right',
                roles: [Role::Proprietor, Role::Bursar],
                order: 32,
                active: 'records.transfers.*',
                mobile: false,
                permission: 'records.transfers.manage',
            ))
            ->add(new NavItem(
                key: 'records.debtor-check',
                label: 'Debtor check',
                route: 'records.debtor-check.index',
                icon: 'shield-check',
                roles: [Role::Proprietor, Role::Bursar],
                order: 33,
                active: 'records.debtor-check.*',
                mobile: false,
                permission: 'records.debtor-check',
            ));
    }

    protected function bootModule(): void
    {
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(StaffMember::class, StaffMemberPolicy::class);
        Gate::policy(TransferRequest::class, TransferRequestPolicy::class);
    }
}
