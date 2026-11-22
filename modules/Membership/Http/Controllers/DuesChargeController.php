<?php

namespace Modules\Membership\Http\Controllers;

use App\Enums\ScopeLevel;
use App\Http\Controllers\Controller;
use App\Support\Scope\ActiveContext;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Membership\Enums\DueStatus;
use Modules\Membership\Http\Requests\DuesChargeRequest;
use Modules\Membership\Models\DuesCharge;
use Modules\Membership\Models\MembershipDue;
use Modules\Membership\Services\DuesLedger;

/**
 * Dues and levies (PRD: Settings - dues, levies). Each executive level sets
 * its own; collection figures are always limited to the viewer's area.
 */
class DuesChargeController extends Controller
{
    public function index(Request $request, ActiveContext $context): View
    {
        Gate::authorize('viewAny', DuesCharge::class);

        $year = $request->integer('year') ?: (int) now()->year;

        $charges = DuesCharge::query()
            ->visibleTo($context)
            ->where('year', $year)
            ->withCount([
                'dues as billed_count' => fn (Builder $q) => $q->where('status', '!=', DueStatus::Void),
                'dues as paid_count' => fn (Builder $q) => $q->where('status', DueStatus::Paid),
            ])
            ->withSum(['dues as expected_kobo' => fn (Builder $q) => $q->where('status', '!=', DueStatus::Void)], 'amount_kobo')
            ->withSum(['dues as collected_kobo' => fn (Builder $q) => $q->where('status', DueStatus::Paid)], 'amount_kobo')
            ->orderByRaw("case scope_type when 'national' then 1 when 'state' then 2 else 3 end")
            ->orderBy('due_date')
            ->get();

        return view('membership::charges.index', [
            'charges' => $charges,
            'year' => $year,
            'years' => range((int) now()->year + 1, (int) now()->year - 3),
            'level' => $context->level(),
        ]);
    }

    public function create(ActiveContext $context): View
    {
        Gate::authorize('create', DuesCharge::class);

        return view('membership::charges.form', [
            'charge' => new DuesCharge([
                'year' => now()->year,
                'kind' => 'annual',
                'due_date' => now()->month >= 10 ? now()->addYear()->startOfYear()->addMonths(2)->endOfMonth() : now()->addMonth()->endOfMonth(),
                'share_national' => $context->level() === ScopeLevel::National ? 100 : 0,
                'share_state' => $context->level() === ScopeLevel::State ? 100 : 0,
                'share_chapter' => $context->level() === ScopeLevel::Chapter ? 100 : 0,
            ]),
            'level' => $context->level() ?? ScopeLevel::National,
            'place' => $context->assignment()?->scopeModel()?->name,
        ]);
    }

    public function store(DuesChargeRequest $request, ActiveContext $context): RedirectResponse
    {
        Gate::authorize('create', DuesCharge::class);

        $level = $request->level();

        $charge = DuesCharge::query()->create([
            ...$request->chargeData(),
            'scope_type' => $level,
            'scope_id' => match ($level) {
                ScopeLevel::State => $context->stateId(),
                ScopeLevel::Chapter => $context->chapterId(),
                default => null,
            },
            'created_by' => $request->user()->getKey(),
        ]);

        return redirect()->route('membership.charges.show', $charge)
            ->with('status', __('Saved as a draft. Check the details, then issue it to schools.'));
    }

    public function show(Request $request, DuesCharge $charge): View
    {
        Gate::authorize('view', $charge);

        $status = DueStatus::tryFrom((string) $request->query('status'));

        $dues = MembershipDue::query()
            ->where('dues_charge_id', $charge->getKey())
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            ->with(['school' => fn ($q) => $q->withoutGlobalScopes()->with('chapter')])
            ->join('schools', 'schools.id', '=', 'membership_dues.school_id')
            ->orderBy('schools.name')
            ->select('membership_dues.*')
            ->paginate(25)
            ->withQueryString();

        $totals = MembershipDue::query()
            ->where('dues_charge_id', $charge->getKey())
            ->where('status', '!=', DueStatus::Void)
            ->toBase()
            ->selectRaw('count(*) as billed, sum(case when status = ? then 1 else 0 end) as paid, coalesce(sum(amount_kobo), 0) as expected, coalesce(sum(case when status = ? then amount_kobo else 0 end), 0) as collected', [DueStatus::Paid->value, DueStatus::Paid->value])
            ->first();

        return view('membership::charges.show', [
            'charge' => $charge,
            'dues' => $dues,
            'status' => $status,
            'totals' => $totals,
            'schoolsToBill' => $charge->isIssued() ? null : $charge->schoolsQuery()->count(),
        ]);
    }

    public function edit(ActiveContext $context, DuesCharge $charge): View
    {
        Gate::authorize('update', $charge);

        return view('membership::charges.form', [
            'charge' => $charge,
            'level' => $charge->scope_type,
            'place' => $context->assignment()?->scopeModel()?->name,
        ]);
    }

    public function update(DuesChargeRequest $request, DuesCharge $charge): RedirectResponse
    {
        Gate::authorize('update', $charge);

        $charge->update($request->chargeData());

        return redirect()->route('membership.charges.show', $charge)->with('status', __('Changes saved.'));
    }

    public function issue(Request $request, DuesCharge $charge, DuesLedger $ledger): RedirectResponse
    {
        Gate::authorize('issue', $charge);

        try {
            $ledger->issue($charge, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('membership.charges.show', $charge)->with('status', __(':title issued. Schools are being billed and their proprietors notified by SMS.', ['title' => $charge->title]));
    }

    public function cancel(DuesCharge $charge, DuesLedger $ledger): RedirectResponse
    {
        Gate::authorize('cancel', $charge);

        $wasIssued = $charge->isIssued();
        $voided = $ledger->cancel($charge);

        return redirect()->route('membership.charges.index', ['year' => $charge->year])->with('status', $wasIssued
            ? __('Withdrawn. :count unpaid bills cancelled; payments already made stay on record.', ['count' => $voided])
            : __('Draft set aside. Nothing was billed.'));
    }
}
