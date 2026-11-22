<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\SendsOtp;
use App\Http\Controllers\Controller;
use App\Services\Auth\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * "Sudo mode" by SMS code, for accounts that have no password.
 *
 * Sets the same session timestamp as Fortify's password confirmation, so the
 * standard password.confirm middleware (used to guard 2FA changes) accepts it.
 */
class ConfirmIdentityController extends Controller
{
    use SendsOtp;

    public function send(Request $request, OtpService $otp): RedirectResponse
    {
        $this->sendOtp($request, $otp, $request->user()->phone, OtpService::PURPOSE_CONFIRM);

        return back()->with('otp_sent', true);
    }

    public function verify(Request $request, OtpService $otp): RedirectResponse
    {
        if ($request->session()->get('otp.phone') !== $request->user()->phone) {
            return back()->withErrors(['code' => __('Request a new code first.')]);
        }

        $this->checkOtp($request, $otp, OtpService::PURPOSE_CONFIRM);

        $request->session()->put('auth.password_confirmed_at', Date::now()->unix());

        return redirect()->intended(route('account.show'));
    }
}
