<?php

namespace App\Http\Controllers\Payments;

use App\Enums\GatewayStatus;
use App\Http\Controllers\Controller;
use App\Models\GatewayTransaction;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where the gateway sends the payer back after checkout. Verifies straight
 * away so the payer sees the result within seconds (PRD MEM-3: < 1 minute),
 * then returns them to the screen that started the payment.
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): RedirectResponse
    {
        $reference = (string) ($request->query('reference') ?? $request->query('trxref'));

        $transaction = GatewayTransaction::query()->where('reference', $reference)->first();

        abort_if($transaction === null, 404);

        $flash = [];

        try {
            $transaction = $payments->refresh($transaction);
        } catch (PaymentGatewayException $e) {
            report($e);
            $flash = ['warning' => __('We could not confirm your payment yet. We will keep checking and update this page; you do not need to pay again.')];
        }

        $flash = $flash ?: match ($transaction->status) {
            GatewayStatus::Success => ['status' => __('Payment received. Thank you!')],
            GatewayStatus::Failed => ['error' => __('The payment did not go through: :reason', ['reason' => $transaction->gateway_response ?: __('declined')])],
            GatewayStatus::Abandoned => ['warning' => __('Payment cancelled. Nothing was charged.')],
            GatewayStatus::Pending => ['warning' => __('Your payment is still processing. This page updates once the bank confirms it.')],
        };

        $returnTo = $transaction->meta('return_url');

        return redirect()->to(is_string($returnTo) ? $returnTo : route('dashboard'))->with($flash);
    }
}
