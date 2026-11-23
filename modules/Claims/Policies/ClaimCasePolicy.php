<?php

namespace Modules\Claims\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Claims\Enums\ClaimStage;
use Modules\Claims\Models\ClaimCase;

/**
 * Who may see and work a claim. Everything is decided from the *active* role
 * (ActiveContext), so switching role changes what a person can do.
 */
class ClaimCasePolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    /** The claims list: anyone whose role opens, oversees or handles claims. */
    public function viewAny(User $user): bool
    {
        return $this->context->allows('claims.open')
            || $this->context->allows('claims.escalated.view')
            || $this->context->allows('claims.cases.handle');
    }

    public function view(User $user, ClaimCase $case): bool
    {
        return $this->viewAny($user)
            && ClaimCase::query()->visibleTo($this->context)->whereKey($case->getKey())->exists();
    }

    /** Open a new claim (CLM-1). */
    public function create(User $user): bool
    {
        return $this->context->allows('claims.open');
    }

    /**
     * Work the file: stage, court details, notes, hearings, settlements and
     * fees. The assigned panel lawyer, the secretariat, and chapter executives
     * (for mediation) may.
     */
    public function handle(User $user, ClaimCase $case): bool
    {
        if (! $this->view($user, $case)) {
            return false;
        }

        if ($this->context->is(Role::Secretariat)) {
            return true;
        }

        if ($this->context->allows('claims.cases.handle') && $case->lawyer_id === $user->getKey()) {
            return true;
        }

        // Chapter mediation happens before the matter goes to court.
        return $this->context->allows('claims.mediate')
            && in_array($case->stage, [ClaimStage::DemandSent, ClaimStage::ResponseWindow, ClaimStage::Mediation], true);
    }

    /** Add documents or generate the demand letter: the legal team and the school that owns the claim. */
    public function contribute(User $user, ClaimCase $case): bool
    {
        return $this->handle($user, $case)
            || ($this->context->allows('claims.open') && $this->view($user, $case));
    }

    /** Pick the panel lawyer for a claim. */
    public function assign(User $user, ClaimCase $case): bool
    {
        return $this->context->is(Role::Secretariat, Role::NationalExecutive) && $this->view($user, $case);
    }

    /** Switch debt claims on or off per state. */
    public function manageSettings(User $user): bool
    {
        return $this->context->is(Role::Secretariat, Role::NationalExecutive);
    }

    /** Whether the active role sees internal lawyer notes. */
    public function viewNotes(User $user, ClaimCase $case): bool
    {
        return ! $this->context->is(Role::Proprietor, Role::Bursar, Role::Staff, Role::Parent);
    }
}
