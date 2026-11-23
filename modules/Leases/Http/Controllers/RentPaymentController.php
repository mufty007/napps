<?php

namespace Modules\Leases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Money;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Modules\Leases\Models\Lease;
use Modules\Leases\Rules\NairaAmount;
use Modules\Leases\Services\LeaseService;

/** The rent log on a lease. Entries are added, never edited, so the audit trail stays honest. */
class RentPaymentController extends Controller
{
    public function __construct(private readonly LeaseService $leases) {}

    public function store(Request $request, Lease $lease): RedirectResponse
    {
        Gate::authorize('manage', $lease);

        $validated = $request->validate([
            'amount' => ['required', new NairaAmount],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->leases->recordRent(
                $lease,
                $request->user(),
                (int) Money::toKobo($validated['amount']),
                Carbon::parse($validated['paid_at']),
                $validated['method'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('leases.show', $lease)->with('status', __('Rent payment recorded.'));
    }
}
