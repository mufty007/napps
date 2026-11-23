<?php

namespace Modules\Fees\Http\Controllers;

use App\Enums\GatewayStatus;
use App\Http\Controllers\Controller;
use App\Models\GatewayTransaction;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Fees\Enums\InvoiceStatus;
use Modules\Fees\Models\FeeAgreement;
use Modules\Fees\Models\FeeInvoice;
use Modules\Fees\Services\FeeInvoiceService;

/**
 * FEE-3: the parent portal. A parent is a personal-scope role, so the school
 * scope hides everything from them; they reach exactly the invoices
 * addressed to their own account (see the "parentInvoice" route binding).
 */
class ParentInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('fees.pay');

        $invoices = $this->mine($request)
            ->with('guardian')
            ->orderByRaw("case when status in ('overdue','partial','issued') then 0 else 1 end")
            ->orderBy('due_date')
            ->get();

        $open = $invoices->filter(fn (FeeInvoice $i) => $i->isOpen());

        return view('fees::parent.index', [
            'open' => $open,
            'settled' => $invoices->reject(fn (FeeInvoice $i) => $i->isOpen()),
            'owedKobo' => (int) $open->sum(fn (FeeInvoice $i) => $i->balanceKobo()),
            'schools' => School::withoutGlobalScopes()->whereIn('id', $invoices->pluck('school_id')->unique())->pluck('name', 'id'),
            'agreements' => FeeAgreement::withoutGlobalScopes()->where('guardian_user_id', $request->user()->getKey())->latest('signed_at')->get(),
        ]);
    }

    public function show(Request $request, FeeInvoice $parentInvoice): View
    {
        Gate::authorize('viewOwn', $parentInvoice);

        $parentInvoice->load(['payments', 'structure']);

        return view('fees::parent.show', [
            'invoice' => $parentInvoice,
            'school' => School::withoutGlobalScopes()->find($parentInvoice->school_id),
            'processing' => GatewayTransaction::query()
                ->where('purpose', FeeInvoiceService::PURPOSE)
                ->where('payer_id', $request->user()->getKey())
                ->where('school_id', $parentInvoice->school_id)
                ->where('status', GatewayStatus::Pending)
                ->where('created_at', '>=', now()->subMinutes(30))
                ->whereNotNull('verified_at')
                ->get()
                ->contains(fn (GatewayTransaction $t) => $t->meta('invoice_id') === $parentInvoice->getKey()),
            'hasAgreement' => FeeAgreement::withoutGlobalScopes()
                ->where('guardian_user_id', $request->user()->getKey())
                ->where('school_id', $parentInvoice->school_id)
                ->exists(),
        ]);
    }

    /**
     * @return Builder<FeeInvoice>
     */
    private function mine(Request $request): Builder
    {
        return FeeInvoice::withoutGlobalScopes()
            ->where('guardian_user_id', $request->user()->getKey())
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->where('status', '!=', InvoiceStatus::Void->value);
    }
}
