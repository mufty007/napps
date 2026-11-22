<?php

namespace Modules\Membership\Policies;

use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Membership\Models\DuesCharge;

/**
 * Every executive level sets its own dues and levies. A level can see the
 * charges above it that bill its schools, but only change its own; the
 * secretariat can fix any of them.
 */
class DuesChargePolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('membership.dues.configure');
    }

    public function view(User $user, DuesCharge $charge): bool
    {
        return $this->viewAny($user)
            && DuesCharge::query()->visibleTo($this->context)->whereKey($charge->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, DuesCharge $charge): bool
    {
        return ! $charge->isIssued() && ! $charge->isCancelled() && $this->owns($user, $charge);
    }

    public function issue(User $user, DuesCharge $charge): bool
    {
        return $this->update($user, $charge);
    }

    public function cancel(User $user, DuesCharge $charge): bool
    {
        return ! $charge->isCancelled() && $this->owns($user, $charge);
    }

    private function owns(User $user, DuesCharge $charge): bool
    {
        return $this->viewAny($user)
            && ($this->context->isAt($charge->scope_type, $charge->scope_id) || $user->can('platform.admin'));
    }
}
