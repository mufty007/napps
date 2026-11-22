<?php

namespace Modules\Membership\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Scope\ActiveContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Membership\Enums\ApplicationStatus;
use Modules\Membership\Models\MembershipApplication;
use Modules\Membership\Services\ApplicationWorkflow;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MEM-2: chapter executives verify, state executives approve. Applications
 * bind through the school scope, so each level only sees its own area.
 */
class ApplicationReviewController extends Controller
{
    public function index(Request $request, ActiveContext $context): View
    {
        Gate::authorize('viewAny', MembershipApplication::class);

        $default = $context->allows('membership.applications.verify') || ! $context->allows('membership.applications.approve')
            ? ApplicationStatus::Submitted
            : ApplicationStatus::Verified;
        $status = ApplicationStatus::tryFrom((string) $request->query('status')) ?? $default;

        $counts = MembershipApplication::query()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('membership::applications.index', [
            'status' => $status,
            'counts' => $counts,
            'applications' => MembershipApplication::query()
                ->with(['school.chapter', 'school.state', 'applicant'])
                ->where('status', $status)
                ->orderBy($status->isOpen() ? 'submitted_at' : 'updated_at', $status->isOpen() ? 'asc' : 'desc')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(MembershipApplication $application): View
    {
        Gate::authorize('view', $application);

        $application->load(['school.chapter', 'school.state', 'applicant', 'verifier', 'approver', 'rejecter', 'media']);

        return view('membership::applications.show', [
            'application' => $application,
            'stateApprovalRequired' => (bool) config('napps.membership.state_approval_required', true),
        ]);
    }

    public function verify(Request $request, MembershipApplication $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('verify', $application);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return $this->attempt(function () use ($workflow, $application, $request, $validated) {
            $application = $workflow->verify($application, $request->user(), $validated['note'] ?? null);

            return $application->status === ApplicationStatus::Approved
                ? __(':school is approved. The certificate has been sent to the proprietor.', ['school' => $application->school->name])
                : __(':school is verified and has gone to the state for approval.', ['school' => $application->school->name]);
        });
    }

    public function approve(Request $request, MembershipApplication $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('approve', $application);

        return $this->attempt(function () use ($workflow, $application, $request) {
            $workflow->approve($application, $request->user());

            return __(':school is now a member, number :number. The certificate has been sent to the proprietor.', [
                'school' => $application->school->name,
                'number' => $application->school->fresh()?->membership_number,
            ]);
        });
    }

    public function reject(Request $request, MembershipApplication $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('reject', $application);

        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], [
            'reason.required' => __('Tell the applicant why, so they can fix it and apply again.'),
        ]);

        return $this->attempt(function () use ($workflow, $application, $request, $validated) {
            $workflow->reject($application, $request->user(), $validated['reason']);

            return __('Application rejected. The applicant has been told why.');
        });
    }

    public function document(MembershipApplication $application, string $collection): StreamedResponse
    {
        Gate::authorize('view', $application);
        abort_unless(array_key_exists($collection, MembershipApplication::DOCUMENTS), 404);

        $media = $application->getFirstMedia($collection);

        abort_if($media === null, 404);

        activity('membership')->performedOn($application)->event('document-viewed')->withProperties(['collection' => $collection])->log('Viewed application document');

        return $media->toInlineResponse(request());
    }

    /**
     * @param  callable(): string  $action
     */
    private function attempt(callable $action): RedirectResponse
    {
        try {
            $message = $action();
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('membership.applications.index')->with('status', $message);
    }
}
