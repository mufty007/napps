<?php

namespace Modules\Membership\Services;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\School;
use App\Models\User;
use Closure;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Membership\Models\MembershipDue;
use Modules\Membership\Models\MembershipSuspension;
use Modules\Membership\Notifications\MembershipStatusChanged;

/**
 * Compliance status for member schools (PRD: Active, Owing, Suspended).
 *
 * A member school's status is derived, never typed in:
 *   Suspended  it has a suspension that has not been lifted
 *   Owing      otherwise, some dues are unpaid past their due date
 *   Active     otherwise
 *
 * Pending applicants are left alone. Only schools whose status actually
 * changes are saved, so each change is audit-logged on the school.
 */
class ComplianceService
{
    /**
     * Recompute member schools, optionally narrowed (e.g. to one chapter).
     *
     * @param  (Closure(Builder<School>): mixed)|null  $constrain
     * @return int Schools whose status changed.
     */
    public function refresh(?Closure $constrain = null): int
    {
        $this->liftSettledAutomaticSuspensions($constrain);
        $this->applyAutomaticSuspensions($constrain);

        $members = fn () => School::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('membership_status', MembershipStatus::members())
            ->when($constrain, fn (Builder $q) => $constrain($q));
        $suspended = fn () => MembershipSuspension::withoutGlobalScopes()->select('school_id')->whereNull('lifted_at');
        $overdue = fn () => MembershipDue::withoutGlobalScopes()->select('school_id')->overdue();

        $targets = [
            [MembershipStatus::Suspended, $members()->whereIn('id', $suspended())],
            [MembershipStatus::Owing, $members()->whereNotIn('id', $suspended())->whereIn('id', $overdue())],
            [MembershipStatus::Active, $members()->whereNotIn('id', $suspended())->whereNotIn('id', $overdue())],
        ];

        $changed = 0;

        foreach ($targets as [$status, $query]) {
            $query->where('membership_status', '!=', $status)
                ->lazyById(200)
                ->each(function (School $school) use ($status, &$changed) {
                    $school->membership_status = $status;
                    $school->save();
                    $changed++;
                });
        }

        return $changed;
    }

    public function refreshSchool(School $school): MembershipStatus
    {
        $this->refresh(fn (Builder $q) => $q->whereKey($school->getKey()));

        /** @var MembershipStatus $status */
        $status = School::withoutGlobalScopes()->whereKey($school->getKey())->value('membership_status');

        // Keep the caller's model in step without marking it dirty.
        $school->membership_status = $status;
        $school->syncOriginalAttribute('membership_status');

        return $status;
    }

    /**
     * An executive suspends a member school (with a reason the school sees).
     */
    public function suspend(School $school, User $by, string $reason): MembershipSuspension
    {
        if (! $school->membership_status->isMember()) {
            throw new DomainException(__('Only approved member schools can be suspended.'));
        }

        $suspension = DB::transaction(function () use ($school, $by, $reason) {
            $suspension = MembershipSuspension::withoutGlobalScopes()->create([
                'school_id' => $school->getKey(),
                'reason' => $reason,
                'automatic' => false,
                'suspended_by' => $by->getKey(),
                'suspended_at' => now(),
            ]);

            $this->refreshSchool($school);

            return $suspension;
        });

        $this->notifyProprietors($school, new MembershipStatusChanged($school, suspended: true, reason: $reason));

        return $suspension;
    }

    /**
     * Lift every open suspension. If dues are still overdue past the
     * automatic threshold, the nightly run will suspend the school again.
     */
    public function reinstate(School $school, User $by, ?string $note = null): int
    {
        $lifted = DB::transaction(function () use ($school, $by, $note) {
            $lifted = 0;

            MembershipSuspension::withoutGlobalScopes()
                ->where('school_id', $school->getKey())
                ->whereNull('lifted_at')
                ->each(function (MembershipSuspension $suspension) use ($by, $note, &$lifted) {
                    $suspension->update(['lifted_at' => now(), 'lifted_by' => $by->getKey(), 'lift_note' => $note]);
                    $lifted++;
                });

            $this->refreshSchool($school);

            return $lifted;
        });

        if ($lifted > 0) {
            $this->notifyProprietors($school, new MembershipStatusChanged($school, suspended: false, reason: $note));
        }

        return $lifted;
    }

    public function isSuspended(School $school): bool
    {
        return MembershipSuspension::withoutGlobalScopes()
            ->where('school_id', $school->getKey())
            ->whereNull('lifted_at')
            ->exists();
    }

    /**
     * @param  (Closure(Builder<School>): mixed)|null  $constrain
     */
    private function applyAutomaticSuspensions(?Closure $constrain): void
    {
        $days = config('napps.membership.suspend_after_days_overdue');

        if (! $days) {
            return;
        }

        $cutoff = today()->subDays((int) $days)->toDateString();

        School::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('membership_status', [MembershipStatus::Active, MembershipStatus::Owing])
            ->when($constrain, fn (Builder $q) => $constrain($q))
            ->whereIn('id', MembershipDue::withoutGlobalScopes()->select('school_id')->unpaid()->where('due_date', '<=', $cutoff))
            ->whereNotIn('id', MembershipSuspension::withoutGlobalScopes()->select('school_id')->whereNull('lifted_at'))
            ->lazyById(200)
            ->each(function (School $school) use ($days) {
                MembershipSuspension::withoutGlobalScopes()->create([
                    'school_id' => $school->getKey(),
                    'reason' => "Dues more than {$days} days overdue",
                    'automatic' => true,
                    'suspended_at' => now(),
                ]);

                $this->notifyProprietors($school, new MembershipStatusChanged($school, suspended: true, reason: __('Dues more than :days days overdue', ['days' => $days])));
            });
    }

    /**
     * Automatic suspensions end by themselves once nothing is overdue past
     * the threshold (or at all, if the rule has since been switched off).
     *
     * @param  (Closure(Builder<School>): mixed)|null  $constrain
     */
    private function liftSettledAutomaticSuspensions(?Closure $constrain): void
    {
        $days = config('napps.membership.suspend_after_days_overdue');
        $cutoff = today()->subDays((int) ($days ?: 0))->toDateString();

        MembershipSuspension::withoutGlobalScopes()
            ->whereNull('lifted_at')
            ->where('automatic', true)
            ->when($constrain, fn (Builder $q) => $q->whereIn(
                'school_id',
                tap(School::withoutGlobalScopes()->select('id'), fn (Builder $schools) => $constrain($schools)),
            ))
            ->whereNotIn('school_id', MembershipDue::withoutGlobalScopes()->select('school_id')->unpaid()->where('due_date', '<=', $cutoff))
            ->each(fn (MembershipSuspension $suspension) => $suspension->update([
                'lifted_at' => now(),
                'lift_note' => 'Overdue dues settled',
            ]));
    }

    private function notifyProprietors(School $school, object $notification): void
    {
        Notification::send(
            User::query()->holdingRole(Role::Proprietor, ScopeLevel::School, $school->getKey())->get(),
            $notification,
        );
    }
}
