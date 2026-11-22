<?php

namespace Modules\Membership\Http\Composers;

use App\Models\School;
use App\Support\Scope\ActiveContext;
use Illuminate\View\View;
use Modules\Membership\Models\DuesPayment;
use Modules\Membership\Models\MembershipDue;
use Modules\Membership\Models\MembershipSuspension;
use Modules\Membership\Services\MembershipDocuments;

/**
 * Proprietor home (PRD): "NAPPS dues status".
 */
class SchoolWidget
{
    public function __construct(
        private readonly ActiveContext $context,
        private readonly MembershipDocuments $documents,
    ) {}

    public function compose(View $view): void
    {
        $school = School::query()->find($this->context->schoolId());

        if (! $school) {
            $view->with('membershipSchool', null);

            return;
        }

        $unpaid = MembershipDue::query()->where('school_id', $school->getKey())->unpaid()->orderBy('due_date')->get();

        $view->with([
            'membershipSchool' => $school,
            'outstandingKobo' => (int) $unpaid->sum('amount_kobo'),
            'overdueCount' => $unpaid->filter->isOverdue()->count(),
            'nextDueDate' => $unpaid->first()?->due_date,
            'lastPayment' => DuesPayment::query()->where('school_id', $school->getKey())->latest('paid_at')->first(),
            'suspension' => MembershipSuspension::query()->where('school_id', $school->getKey())->whereNull('lifted_at')->latest('suspended_at')->first(),
            'certificateUrl' => $school->membership_number && $school->membership_status->isMember()
                ? $this->documents->certificateUrl($school, 1)
                : null,
            'canPayDues' => $this->context->allows('membership.dues.pay'),
        ]);
    }
}
