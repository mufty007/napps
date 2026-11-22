<?php

namespace Modules\Membership\Policies;

use App\Models\User;
use Modules\Membership\Enums\ApplicationStatus;
use Modules\Membership\Models\MembershipApplication;

/**
 * Who may act on an application. *Where* is already enforced: executive
 * routes bind applications through the school scope, so an application
 * outside the active role's area is a 404 before any policy runs.
 */
class MembershipApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('membership.applications.verify') || $user->can('membership.applications.approve');
    }

    public function view(User $user, MembershipApplication $application): bool
    {
        return $application->applicant_id === $user->getKey()
            || $this->viewAny($user)
            || $user->can('org.view');
    }

    public function verify(User $user, MembershipApplication $application): bool
    {
        return $application->status === ApplicationStatus::Submitted
            && $user->can('membership.applications.verify');
    }

    public function approve(User $user, MembershipApplication $application): bool
    {
        return $application->status === ApplicationStatus::Verified
            && $user->can('membership.applications.approve');
    }

    public function reject(User $user, MembershipApplication $application): bool
    {
        return $this->verify($user, $application) || $this->approve($user, $application);
    }
}
