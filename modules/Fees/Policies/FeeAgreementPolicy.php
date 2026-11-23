<?php

namespace Modules\Fees\Policies;

use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Fees\Models\FeeAgreement;

/**
 * A parent signs and re-reads their own agreements; the school's staff can
 * read the ones signed with their school.
 */
class FeeAgreementPolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allows('fees.invoices.manage');
    }

    public function view(User $user, FeeAgreement $agreement): bool
    {
        if ($this->context->allows('fees.pay') && $agreement->guardian_user_id === $user->getKey()) {
            return true;
        }

        return $this->viewAny($user) && FeeAgreement::query()->whereKey($agreement->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $this->context->allows('fees.pay');
    }
}
