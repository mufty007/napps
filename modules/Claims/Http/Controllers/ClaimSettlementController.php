<?php

namespace Modules\Claims\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Claims\Http\Controllers\Concerns\RespondsToAjax;
use Modules\Claims\Models\ClaimCase;
use Modules\Claims\Rules\NairaAmount;
use Modules\Claims\Services\ClaimService;

/** Settlements agreed on a claim, and the legal fees charged against it. */
class ClaimSettlementController extends Controller
{
    use RespondsToAjax;

    public function __construct(private readonly ClaimService $claims) {}

    public function store(Request $request, ClaimCase $case): RedirectResponse|JsonResponse
    {
        Gate::authorize('handle', $case);

        $validated = $request->validate([
            'amount' => ['required', new NairaAmount],
            'terms' => ['nullable', 'string', 'max:5000'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $this->claims->recordSettlement(
            $case,
            $request->user(),
            (int) Money::toKobo($validated['amount']),
            $validated['terms'] ?? null,
            filled($validated['paid_at'] ?? null) ? CarbonImmutable::parse($validated['paid_at']) : null,
        );

        return $this->respond($request, __('Settlement recorded.'));
    }

    public function fee(Request $request, ClaimCase $case): RedirectResponse|JsonResponse
    {
        Gate::authorize('handle', $case);

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', new NairaAmount],
        ]);

        $this->claims->recordFee($case, $request->user(), $validated['description'], (int) Money::toKobo($validated['amount']));

        return $this->respond($request, __('Fee recorded.'));
    }
}
