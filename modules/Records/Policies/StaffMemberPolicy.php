<?php

namespace Modules\Records\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Records\Models\StaffMember;

class StaffMemberPolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allows('records.staff.manage');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user)
            && ($this->context->schoolId() !== null || $this->context->is(Role::Secretariat));
    }

    public function update(User $user, StaffMember $member): bool
    {
        return $this->viewAny($user) && StaffMember::query()->whereKey($member->getKey())->exists();
    }

    public function delete(User $user, StaffMember $member): bool
    {
        return $this->update($user, $member);
    }
}
