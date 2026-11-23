<?php

namespace Modules\Fees\Policies;

use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Fees\Models\FeeStructure;

/**
 * Proprietors set the school's fee structures; bursars can read them to
 * raise invoices.
 */
class FeeStructurePolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allows('fees.structures.manage') || $this->context->allows('fees.invoices.manage');
    }

    public function create(User $user): bool
    {
        return $this->context->allows('fees.structures.manage') && $this->context->schoolId() !== null;
    }

    public function update(User $user, FeeStructure $structure): bool
    {
        return $this->context->allows('fees.structures.manage')
            && FeeStructure::query()->whereKey($structure->getKey())->exists();
    }

    public function delete(User $user, FeeStructure $structure): bool
    {
        return $this->update($user, $structure);
    }
}
