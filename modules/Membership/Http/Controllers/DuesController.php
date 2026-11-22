<?php

namespace Modules\Membership\Http\Controllers;

use App\Enums\GatewayStatus;
use App\Http\Controllers\Controller;
use App\Models\GatewayTransaction;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Services\Payments\PaymentGatewayException;
use App\Support\Scope\ActiveContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Membership\Models\DuesPayment;
use Modules\Membership\Models\MembershipDue;
use Modules\Membership\Models\MembershipSuspension;
use Modules\Membership\Services\DuesLedger;
use Modules\Membership\Services\MembershipDocuments;

/**
 * MEM-3: the proprietor's NAPPS membership screen for the active school -
 * status, certificate, dues to pay online, receipts.
 */
class DuesController extends Controller
{
    public function index(Request $request, ActiveContext $context, MembershipDocuments $documents): View|RedirectResponse
    {
        // Links in SMS carry ?school=ID: switch to that school if the person works there.
        if ($request->filled('school') && $request->integer('school') !== $context->schoolId()) {
            $assignment = RoleAssignment::query()
                ->where('user_id', $request->user()->getKey())
                ->where('scope_type', 'school')
                ->where('scope_id', $request->integer('school'))
                ->get()
                ->first(fn (RoleAssignment $a) => $a->role?->hasPermissionTo('membership.dues.pay'));

            if ($assignment) {
                $context->switchTo($assignment);
            }

            return redirect()->route('membership.dues.index');
        }

        Gate::authorize('membership.dues.pay');

        $school = School::query()->with(['chapter', 'state'])->findOrFail($context->schoolId());

        return view('membership::dues.index', [
            'school' => $school,
            'unpaid' => MembershipDue::query()->where('school_id', $school->getKey())->unpaid()->with('charge')->orderBy('due_date')->get(),
            'payments' => DuesPayment::query()->where('school_id', $school->getKey())->latest('paid_at')->limit(20)->get(),
            'suspension' => MembershipSuspension::query()->where('school_id', $school->getKey())->whereNull('lifted_at')->latest('suspended_at')->first(),
            'processing' => GatewayTransaction::query()
                ->where('purpose', DuesLedger::PURPOSE)
                ->where('school_id', $school->getKey())
                ->where('status', GatewayStatus::Pending)
                ->where('created_at', '>=', now()->subMinutes(30))
                ->whereNotNull('verified_at')
                ->exists(),
            'certificateUrl' => $school->membership_number && $school->membership_status->isMember() ? $documents->certificateUrl($school, 1) : null,
            'documents' => $documents,
        ]);
    }

    public function pay(Request $request, ActiveContext $context, DuesLedger $ledger): RedirectResponse
    {
        $validated = $request->validate(['dues' => ['nullable', 'array'], 'dues.*' => ['integer']]);
        $school = School::query()->findOrFail($context->schoolId());

        $dues = MembershipDue::query()
            ->where('school_id', $school->getKey())
            ->unpaid()
            ->when($validated['dues'] ?? null, fn ($q, $ids) => $q->whereKey($ids))
            ->get();

        if ($dues->isEmpty()) {
            return back()->with('status', __('There is nothing to pay. Your dues are up to date.'));
        }

        try {
            $transaction = $ledger->startOnlinePayment($school, $request->user(), $dues, route('membership.dues.index'));
        } catch (PaymentGatewayException $e) {
            report($e);

            return back()->with('error', __('We could not open the payment page. Please try again in a few minutes.'));
        }

        return redirect()->away((string) $transaction->authorization_url);
    }
}
