<?php

namespace App\Http\Controllers\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\GatewayStatus;
use App\Http\Controllers\Controller;
use App\Models\GatewayTransaction;
use App\Services\Payments\FakeGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Local-only checkout page for the fake gateway (routes exist only when
 * PAYMENTS_DRIVER=fake outside production).
 */
class FakeCheckoutController extends Controller
{
    public function show(string $reference): View
    {
        return view('payments.fake-checkout', [
            'transaction' => GatewayTransaction::query()->where('reference', $reference)->firstOrFail(),
        ]);
    }

    public function complete(Request $request, string $reference, PaymentGateway $gateway): RedirectResponse
    {
        abort_unless($gateway instanceof FakeGateway, 404);

        $transaction = GatewayTransaction::query()->where('reference', $reference)->firstOrFail();

        $validated = $request->validate([
            'outcome' => ['required', Rule::in([GatewayStatus::Success->value, GatewayStatus::Failed->value, GatewayStatus::Abandoned->value])],
        ]);

        $gateway->setOutcome($transaction->reference, GatewayStatus::from($validated['outcome']));

        return redirect()->route('payments.callback', ['reference' => $transaction->reference]);
    }
}
