<?php

namespace Modules\Surveys\Policies;

use App\Enums\ScopeLevel;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Surveys\Models\Survey;

/**
 * Who may build, run and read surveys. The "surveys.issue" permission says
 * the active role can run surveys at all; the survey's audience says whether
 * this particular one sits inside the role's own place in the org chain.
 */
class SurveyPolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allows('surveys.issue');
    }

    public function create(User $user): bool
    {
        return $this->context->allows('surveys.issue');
    }

    /** Results and the question list. */
    public function view(User $user, Survey $survey): bool
    {
        if (! $this->context->allows('surveys.issue')) {
            return false;
        }

        return $survey->isManageableBy($this->context)
            || ($survey->scope_level === ScopeLevel::National && ! $survey->isDraft());
    }

    /** Edit, add questions, publish, close and delete. */
    public function update(User $user, Survey $survey): bool
    {
        return $this->context->allows('surveys.issue') && $survey->isManageableBy($this->context);
    }

    public function delete(User $user, Survey $survey): bool
    {
        return $this->update($user, $survey);
    }
}
