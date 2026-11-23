<?php

namespace Modules\Leases\Http\Controllers;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Leases\Enums\DisputeStatus;
use Modules\Leases\Models\Lease;
use Modules\Leases\Models\LeaseDispute;
use Modules\Leases\Services\LeaseService;

/**
 * Lease disputes: schools open them; chapter and state executives (and the
 * assigned panel lawyer) help, escalate and resolve them.
 */
class LeaseDisputeController extends Controller
{
    public function __construct(private readonly LeaseService $leases) {}

    public function index(Request $request, ActiveContext $context): View
    {
        Gate::authorize('viewAny', LeaseDispute::class);

        $status = $request->query('status') === 'resolved' ? 'resolved' : 'open';

        $visible = fn () => LeaseDispute::query()->visibleTo($context);

        $disputes = $visible()
            ->when($status === 'resolved', fn ($q) => $q->resolved(), fn ($q) => $q->open())
            ->with(['lease', 'school', 'lawyer'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('leases::disputes.index', [
            'disputes' => $disputes,
            'status' => $status,
            'counts' => ['open' => $visible()->open()->count(), 'resolved' => $visible()->resolved()->count()],
        ]);
    }

    public function show(LeaseDispute $dispute): View
    {
        Gate::authorize('view', $dispute);

        $dispute->load(['lease', 'school.chapter', 'opener', 'lawyer']);

        $canEscalate = Gate::allows('escalate', $dispute) && $dispute->isOpen();

        return view('leases::disputes.show', [
            'dispute' => $dispute,
            'lease' => $dispute->lease,
            'canViewLease' => Gate::allows('view', $dispute->lease),
            'canRequestHelp' => $dispute->status === DisputeStatus::Open && Gate::allows('requestHelp', $dispute),
            'canEscalate' => $canEscalate,
            'canResolve' => $dispute->isOpen() && Gate::allows('resolve', $dispute),
            'lawyers' => $canEscalate ? User::query()->holdingRole(Role::LegalPanel)->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function store(Request $request, Lease $lease): RedirectResponse
    {
        Gate::authorize('manage', $lease);
        Gate::authorize('create', LeaseDispute::class);

        $validated = $request->validate(['summary' => ['required', 'string', 'min:10', 'max:5000']]);

        try {
            $dispute = $this->leases->openDispute($lease, $request->user(), $validated['summary']);
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('leases.disputes.show', $dispute)->with('status', __('Dispute opened. Your chapter has been told.'));
    }

    public function help(Request $request, LeaseDispute $dispute): RedirectResponse
    {
        Gate::authorize('requestHelp', $dispute);

        $this->leases->requestChapterHelp($dispute, $request->user());

        return back()->with('status', __('Marked as being helped by the chapter.'));
    }

    public function escalate(Request $request, LeaseDispute $dispute): RedirectResponse
    {
        Gate::authorize('escalate', $dispute);

        $validated = $request->validate(['lawyer_id' => ['nullable', 'integer', 'exists:users,id']]);

        $lawyer = filled($validated['lawyer_id'] ?? null) ? User::query()->findOrFail($validated['lawyer_id']) : null;

        try {
            $this->leases->escalateToLegal($dispute, $request->user(), $lawyer);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Passed to the legal panel.'));
    }

    public function resolve(Request $request, LeaseDispute $dispute): RedirectResponse
    {
        Gate::authorize('resolve', $dispute);

        $validated = $request->validate(['resolution_notes' => ['required', 'string', 'min:5', 'max:5000']]);

        $this->leases->resolveDispute($dispute, $request->user(), $validated['resolution_notes']);

        return back()->with('status', __('Dispute resolved.'));
    }
}
