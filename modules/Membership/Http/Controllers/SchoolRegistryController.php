<?php

namespace Modules\Membership\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\School;
use App\Models\State;
use App\Models\User;
use App\Support\Money;
use App\Support\Scope\ActiveContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Membership\Enums\PaymentMethod;
use Modules\Membership\Http\Requests\RecordDuesPaymentRequest;
use Modules\Membership\Models\DuesPayment;
use Modules\Membership\Models\MembershipApplication;
use Modules\Membership\Models\MembershipDue;
use Modules\Membership\Models\MembershipSuspension;
use Modules\Membership\Services\ComplianceService;
use Modules\Membership\Services\DuesLedger;
use Modules\Membership\Services\MembershipDocuments;

/**
 * Member registry for executives (PRD: School directory / Chapter list):
 * every school in the active role's area with its dues status.
 */
class SchoolRegistryController extends Controller
{
    public function index(Request $request, ActiveContext $context): View
    {
        $level = $context->level();
        $status = MembershipStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('q'));

        $schools = School::query()
            ->with(['chapter', 'state'])
            ->addSelect(['outstanding_kobo' => MembershipDue::withoutGlobalScopes()
                ->selectRaw('coalesce(sum(amount_kobo), 0)')
                ->whereColumn('membership_dues.school_id', 'schools.id')
                ->unpaid()])
            ->when($status, fn (Builder $q) => $q->where('membership_status', $status), fn (Builder $q) => $q->whereIn('membership_status', MembershipStatus::members()))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('membership_number', 'like', '%'.$search.'%')
                ->orWhere('registration_number', 'like', '%'.$search.'%')))
            ->when($request->integer('state'), fn (Builder $q, int $id) => $q->where('state_id', $id))
            ->when($request->integer('chapter'), fn (Builder $q, int $id) => $q->where('chapter_id', $id))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('membership::schools.index', [
            'schools' => $schools,
            'status' => $status,
            'search' => $search,
            'states' => $level === ScopeLevel::National ? State::query()->orderBy('name')->pluck('name', 'id') : collect(),
            'chapters' => in_array($level, [ScopeLevel::National, ScopeLevel::State], true)
                ? Chapter::query()->when($context->stateId(), fn ($q, $id) => $q->where('state_id', $id))
                    ->when($request->integer('state'), fn ($q, $id) => $q->where('state_id', $id))
                    ->orderBy('name')->pluck('name', 'id')
                : collect(),
        ]);
    }

    public function show(School $school, MembershipDocuments $documents): View
    {
        $school->load(['chapter', 'state']);

        return view('membership::schools.show', [
            'school' => $school,
            'proprietors' => User::query()->holdingRole(Role::Proprietor, ScopeLevel::School, $school->getKey())->get(),
            'dues' => MembershipDue::query()->where('school_id', $school->getKey())->with('charge')->orderByDesc('due_date')->get(),
            'payments' => DuesPayment::query()->where('school_id', $school->getKey())->with('recorder')->latest('paid_at')->limit(20)->get(),
            'suspensions' => MembershipSuspension::query()->where('school_id', $school->getKey())->with(['suspender', 'lifter'])->latest('suspended_at')->get(),
            'application' => MembershipApplication::query()->where('school_id', $school->getKey())->latest('id')->first(),
            'certificateUrl' => $school->membership_number && $school->membership_status->isMember() ? $documents->certificateUrl($school, 1) : null,
            'documents' => $documents,
        ]);
    }

    public function suspend(Request $request, School $school, ComplianceService $compliance): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $compliance->suspend($school, $request->user(), $validated['reason']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __(':school is suspended. Its proprietors have been told why.', ['school' => $school->name]));
    }

    public function reinstate(Request $request, School $school, ComplianceService $compliance): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $compliance->reinstate($school, $request->user(), $validated['note'] ?? null);

        return back()->with('status', __(':school is now :status.', ['school' => $school->name, 'status' => mb_strtolower($school->membership_status->label())]));
    }

    public function recordPayment(RecordDuesPaymentRequest $request, School $school, DuesLedger $ledger): RedirectResponse
    {
        $dues = MembershipDue::query()
            ->where('school_id', $school->getKey())
            ->whereKey($request->validated('dues'))
            ->unpaid()
            ->get();

        if ($dues->isEmpty()) {
            return back()->with('error', __('Those dues are already paid.'));
        }

        $payment = $ledger->recordPayment(
            school: $school,
            dues: $dues,
            method: PaymentMethod::from($request->validated('method')),
            amountKobo: (int) $dues->sum('amount_kobo'),
            paidAt: CarbonImmutable::parse($request->validated('paid_on'))->setTimeFrom(now()),
            reference: $request->validated('reference'),
            recordedBy: $request->user(),
            notes: $request->validated('notes'),
        );

        return back()->with('status', __('Recorded :amount. Receipt :number sent to the proprietor.', [
            'amount' => Money::format($payment->amount_kobo),
            'number' => $payment->receipt_number,
        ]));
    }
}
