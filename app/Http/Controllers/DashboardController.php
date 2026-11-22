<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\ScopeLevel;
use App\Models\Chapter;
use App\Models\School;
use App\Models\State;
use App\Support\Scope\ActiveContext;
use Illuminate\View\View;

/**
 * Each role starts on its own home dashboard (PRD: Key screens). Module
 * phases add their widgets; Phase 0 shows the org data every role has.
 */
class DashboardController extends Controller
{
    public function __invoke(ActiveContext $context): View
    {
        $role = $context->role();

        if (! $role) {
            return view('dashboard.none');
        }

        return match ($role->shell()) {
            'secretariat', 'executive' => view('dashboard.executive', $this->orgSummary($context)),
            'school' => view('dashboard.school', [
                'school' => School::query()->with(['chapter', 'state'])->find($context->schoolId()),
            ]),
            'parent' => view('dashboard.parent'),
            default => view('dashboard.legal'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function orgSummary(ActiveContext $context): array
    {
        $level = $context->level();

        $byStatus = School::query()
            ->toBase()
            ->selectRaw('membership_status, count(*) as total')
            ->groupBy('membership_status')
            ->pluck('total', 'membership_status');

        $statusCounts = collect(MembershipStatus::cases())
            ->mapWithKeys(fn (MembershipStatus $s) => [$s->value => (int) ($byStatus[$s->value] ?? 0)]);

        return [
            'level' => $level,
            'place' => $context->assignment()?->scopeModel(),
            'schoolCount' => $statusCounts->sum(),
            'statusCounts' => $statusCounts,
            'chapterCount' => match ($level) {
                ScopeLevel::National => Chapter::query()->count(),
                ScopeLevel::State => Chapter::query()->where('state_id', $context->stateId())->count(),
                default => null,
            },
            'stateCount' => $level === ScopeLevel::National ? State::query()->count() : null,
            'recentSchools' => School::query()->with('chapter')->latest()->limit(6)->get(),
        ];
    }
}
