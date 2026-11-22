<?php

namespace Modules\Membership\Jobs;

use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\School;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Membership\Models\DuesCharge;
use Modules\Membership\Notifications\DuesIssued;
use Modules\Membership\Services\ComplianceService;
use Modules\Membership\Services\DuesLedger;

/**
 * Bill every school under an issued charge, update compliance and tell
 * proprietors. Runs on the queue: a national charge can reach 20,000 schools.
 */
class IssueDuesCharge implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(public DuesCharge $charge) {}

    public function handle(DuesLedger $ledger, ComplianceService $compliance): void
    {
        $billed = $ledger->bill($this->charge);

        if ($billed === []) {
            return;
        }

        $compliance->refresh(fn (Builder $q) => $q->whereIn('id', $this->charge->schoolsQuery()->select('id')));

        foreach (array_chunk($billed, 200) as $schoolIds) {
            $schools = School::withoutGlobalScopes()->whereKey($schoolIds)->get(['id', 'name'])->keyBy('id');

            RoleAssignment::query()
                ->with('user')
                ->whereHas('role', fn (Builder $q) => $q->where('name', Role::Proprietor->value))
                ->where('scope_type', ScopeLevel::School)
                ->whereIn('scope_id', $schoolIds)
                ->each(function (RoleAssignment $assignment) use ($schools) {
                    $school = $schools->get($assignment->scope_id);

                    if ($school && $assignment->user) {
                        $assignment->user->notify(new DuesIssued($this->charge, $school));
                    }
                });
        }
    }
}
