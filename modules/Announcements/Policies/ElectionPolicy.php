<?php

namespace Modules\Announcements\Policies;

use App\Models\School;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Announcements\Models\Election;

/**
 * Executives (events.manage) run elections inside their own chapter or state;
 * proprietors (elections.vote) cast their school's single vote.
 */
class ElectionPolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allows('events.manage') || $this->context->allows('elections.vote');
    }

    public function create(User $user): bool
    {
        return $this->context->allows('events.manage');
    }

    public function view(User $user, Election $election): bool
    {
        if ($this->context->allows('events.manage')) {
            return $this->manages($election);
        }

        $school = $this->votingSchool();

        return $this->context->allows('elections.vote')
            && $school !== null
            && Election::query()->forSchool($school)->whereKey($election->getKey())->exists();
    }

    public function manage(User $user, Election $election): bool
    {
        return $this->context->allows('events.manage') && $this->manages($election);
    }

    public function vote(User $user, Election $election): bool
    {
        $school = $this->votingSchool();

        return $this->context->allows('elections.vote')
            && $school !== null
            && $election->isOpen()
            && $election->isEligible($school);
    }

    private function manages(Election $election): bool
    {
        return Election::query()->whereKey($election->getKey())->manageableBy($this->context)->exists();
    }

    private function votingSchool(): ?School
    {
        $id = $this->context->schoolId();

        return $id === null ? null : School::withoutGlobalScopes()->find($id);
    }
}
