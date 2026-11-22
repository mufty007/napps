<?php

namespace Modules\Membership\Http\Composers;

use App\Support\Scope\ActiveContext;
use Illuminate\View\View;
use Modules\Membership\Enums\ApplicationStatus;
use Modules\Membership\Models\MembershipApplication;

/**
 * MEM-1: "the status is visible to me" - shown on the applicant's home.
 * Someone who already holds a role (a proprietor adding another school)
 * only sees applications still waiting for a decision.
 */
class ApplicantWidget
{
    public function __construct(private readonly ActiveContext $context) {}

    public function compose(View $view): void
    {
        $applications = MembershipApplication::withoutGlobalScopes()
            ->where('applicant_id', $this->context->user()?->getKey())
            ->when($this->context->role() !== null, fn ($q) => $q->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::Verified]))
            ->with(['school' => fn ($q) => $q->with('chapter')])
            ->latest('id')
            ->limit(5)
            ->get();

        $view->with('membershipApplications', $applications);
    }
}
