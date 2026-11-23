<?php

namespace Modules\Leases\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Leases\Enums\DisputeStatus;
use Modules\Leases\Models\LeaseDispute;

/**
 * Who may see and move a lease dispute, decided from the *active* role:
 * the school that owns it, executives in scope (`leases.disputes.resolve`)
 * and the panel lawyer it is assigned to.
 */
class LeaseDisputePolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allows('leases.manage')
            || $this->context->allows('leases.disputes.resolve')
            || $this->context->is(Role::LegalPanel);
    }

    public function view(User $user, LeaseDispute $dispute): bool
    {
        return $this->viewAny($user)
            && LeaseDispute::query()->visibleTo($this->context)->whereKey($dispute->getKey())->exists();
    }

    /** Open a dispute on one of the school's leases. */
    public function create(User $user): bool
    {
        return $this->context->allows('leases.manage');
    }

    /** Ask the chapter to help: the school, or an executive taking it on. */
    public function requestHelp(User $user, LeaseDispute $dispute): bool
    {
        return $this->view($user, $dispute)
            && ($this->context->allows('leases.manage') || $this->context->allows('leases.disputes.resolve'));
    }

    /** Pass it to the legal panel and name the lawyer. */
    public function escalate(User $user, LeaseDispute $dispute): bool
    {
        return $this->view($user, $dispute) && $this->context->allows('leases.disputes.resolve');
    }

    /**
     * Close it: executives in scope and the assigned lawyer at any point; the
     * school itself only while it has not gone to the legal panel.
     */
    public function resolve(User $user, LeaseDispute $dispute): bool
    {
        if (! $this->view($user, $dispute)) {
            return false;
        }

        if ($this->context->allows('leases.disputes.resolve')) {
            return true;
        }

        if ($this->context->is(Role::LegalPanel)) {
            return $dispute->assigned_lawyer_id === $user->getKey();
        }

        return $this->context->allows('leases.manage')
            && in_array($dispute->status, [DisputeStatus::Open, DisputeStatus::ChapterHelp], true);
    }
}
