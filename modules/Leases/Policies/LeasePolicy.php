<?php

namespace Modules\Leases\Policies;

use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Leases\Models\Lease;

/**
 * The lease register belongs to the school (and the secretariat). Where the
 * active role may act comes from the school scope on Lease; what it may do
 * comes from `leases.manage`. Executives help through disputes, not the register.
 */
class LeasePolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allows('leases.manage');
    }

    public function view(User $user, Lease $lease): bool
    {
        return $this->viewAny($user)
            && Lease::query()->whereKey($lease->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $this->context->allows('leases.manage');
    }

    public function update(User $user, Lease $lease): bool
    {
        return $this->view($user, $lease);
    }

    public function delete(User $user, Lease $lease): bool
    {
        return $this->view($user, $lease);
    }

    /** Log rent, add vault documents, generate the model lease, open disputes. */
    public function manage(User $user, Lease $lease): bool
    {
        return $this->view($user, $lease);
    }
}
