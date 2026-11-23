<?php

namespace Modules\Records\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\Scope\ActiveContext;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Records\Http\Controllers\Concerns\ChoosesSchool;
use Modules\Records\Services\DebtorCheckService;

/**
 * Consent-gated debtor check. The answer is only "clear" or "outstanding" and
 * a total; every check is audit-logged (NDPA).
 */
class DebtorCheckController extends Controller
{
    use ChoosesSchool;

    public function __construct(private readonly DebtorCheckService $checks) {}

    public function index(): View
    {
        Gate::authorize('records.debtor-check');

        return view('records::debtor-check.index', ['result' => null]);
    }

    public function check(Request $request, ActiveContext $context): View
    {
        Gate::authorize('records.debtor-check');

        $validated = $request->validate([
            'guardian_phone' => ['required', 'string', 'max:30', $this->phoneRule()],
            'consent_confirmed' => ['accepted'],
        ], [
            'consent_confirmed.accepted' => __('Confirm that the guardian agreed to this check.'),
        ]);

        try {
            $result = $this->checks->check(
                $request->user(),
                $validated['guardian_phone'],
                $context->schoolId() ? [$context->schoolId()] : [],
            );
        } catch (DomainException $e) {
            return view('records::debtor-check.index', ['result' => null])->withErrors(['guardian_phone' => $e->getMessage()]);
        }

        return view('records::debtor-check.index', [
            'result' => $result + [
                'amount' => Money::format($result['amount_kobo']),
                'phone' => PhoneNumber::format(PhoneNumber::normalize($validated['guardian_phone'])),
            ],
        ]);
    }
}
