<?php

namespace Modules\Membership\Http\Composers;

use App\Enums\MembershipStatus;
use App\Enums\ScopeLevel;
use App\Models\Chapter;
use App\Models\School;
use App\Models\State;
use App\Support\Scope\ActiveContext;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Membership\Enums\ApplicationStatus;
use Modules\Membership\Enums\DueStatus;
use Modules\Membership\Models\DuesAllocation;
use Modules\Membership\Models\DuesCharge;
use Modules\Membership\Models\MembershipApplication;
use Modules\Membership\Models\MembershipDue;

/**
 * Executive homes (PRD: Key screens):
 *   national  dues collected vs expected, compliance % by state
 *   state     chapters ranked by compliance, pending approvals
 *   chapter   schools and their dues status, schools to verify
 * Every figure goes through the org scopes, so each level sees its own area.
 */
class ExecutiveWidget
{
    public function __construct(private readonly ActiveContext $context) {}

    public function compose(View $view): void
    {
        $year = (int) now()->year;
        $level = $this->context->level();

        $view->with([
            'duesYear' => $year,
            'reviewQueue' => $this->reviewQueue(),
            'dues' => $this->dues($year),
            'levelShare' => $this->levelShare($year, $level),
            'ranking' => $this->ranking($level),
            'rankingBy' => match ($level) {
                ScopeLevel::National => 'state',
                ScopeLevel::State => 'chapter',
                default => null,
            },
            'followUp' => $level === ScopeLevel::Chapter ? $this->followUp() : collect(),
        ]);
    }

    /**
     * @return array{status: ApplicationStatus, count: int}|null
     */
    private function reviewQueue(): ?array
    {
        $status = match (true) {
            $this->context->allows('membership.applications.verify') => ApplicationStatus::Submitted,
            $this->context->allows('membership.applications.approve') => ApplicationStatus::Verified,
            default => null,
        };

        return $status ? ['status' => $status, 'count' => MembershipApplication::query()->where('status', $status)->count()] : null;
    }

    /**
     * @return array{expected: int, collected: int}
     */
    private function dues(int $year): array
    {
        $row = MembershipDue::query()
            ->whereIn('dues_charge_id', DuesCharge::query()->select('id')->where('year', $year))
            ->where('status', '!=', DueStatus::Void)
            ->toBase()
            ->selectRaw('coalesce(sum(amount_kobo), 0) as expected, coalesce(sum(case when status = ? then amount_kobo else 0 end), 0) as collected', [DueStatus::Paid->value])
            ->first();

        return ['expected' => (int) ($row->expected ?? 0), 'collected' => (int) ($row->collected ?? 0)];
    }

    /**
     * What this level actually keeps from the split ledger this year.
     *
     * @return array{label: string, kobo: int}|null
     */
    private function levelShare(int $year, ?ScopeLevel $level): ?array
    {
        $query = DuesAllocation::query()
            ->whereIn('membership_due_id', MembershipDue::withoutGlobalScopes()->select('id')
                ->whereIn('dues_charge_id', DuesCharge::query()->select('id')->where('year', $year)));

        return match ($level) {
            ScopeLevel::National => ['label' => __("National's share"), 'kobo' => (int) $query->where('level', ScopeLevel::National)->sum('amount_kobo')],
            ScopeLevel::State => ['label' => __(":place's share", ['place' => State::query()->whereKey($this->context->stateId())->value('name')]), 'kobo' => (int) $query->where('level', ScopeLevel::State)->where('state_id', $this->context->stateId())->sum('amount_kobo')],
            ScopeLevel::Chapter => ['label' => __(":place's share", ['place' => Chapter::query()->whereKey($this->context->chapterId())->value('name')]), 'kobo' => (int) $query->where('level', ScopeLevel::Chapter)->where('chapter_id', $this->context->chapterId())->sum('amount_kobo')],
            default => null,
        };
    }

    /**
     * Compliance (% of members Active) by state for national, by chapter for a state.
     *
     * @return Collection<int, array{id: int, name: string, members: int, active: int, percent: int}>
     */
    private function ranking(?ScopeLevel $level): Collection
    {
        $column = match ($level) {
            ScopeLevel::National => 'state_id',
            ScopeLevel::State => 'chapter_id',
            default => null,
        };

        if ($column === null) {
            return collect();
        }

        $rows = School::query()
            ->whereIn('membership_status', MembershipStatus::members())
            ->toBase()
            ->selectRaw("{$column} as unit_id, count(*) as members, sum(case when membership_status = ? then 1 else 0 end) as active", [MembershipStatus::Active->value])
            ->groupBy($column)
            ->get();

        $names = ($column === 'state_id' ? State::query() : Chapter::query())
            ->whereKey($rows->pluck('unit_id'))
            ->pluck('name', 'id');

        return $rows
            ->map(fn (object $row) => [
                'id' => (int) $row->unit_id,
                'name' => (string) ($names[$row->unit_id] ?? '—'),
                'members' => (int) $row->members,
                'active' => (int) $row->active,
                'percent' => $row->members > 0 ? (int) round($row->active / $row->members * 100) : 0,
            ])
            ->sortBy([['percent', 'desc'], ['members', 'desc']])
            ->values();
    }

    /**
     * Chapter: schools to follow up on dues.
     *
     * @return Collection<int, School>
     */
    private function followUp(): Collection
    {
        return School::query()
            ->whereIn('membership_status', [MembershipStatus::Owing, MembershipStatus::Suspended])
            ->addSelect(['outstanding_kobo' => MembershipDue::withoutGlobalScopes()
                ->selectRaw('coalesce(sum(amount_kobo), 0)')
                ->whereColumn('membership_dues.school_id', 'schools.id')
                ->unpaid()])
            ->orderByDesc('outstanding_kobo')
            ->limit(6)
            ->get();
    }
}
