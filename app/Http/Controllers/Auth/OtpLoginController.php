<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\SendsOtp;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\NigerianPhone;
use App\Services\Auth\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Primary sign-in: phone number + SMS code. Users with 2FA turned on are
 * handed to Fortify's two-factor challenge after the code checks out.
 */
class OtpLoginController extends Controller
{
    use SendsOtp;

    public function send(Request $request, OtpService $otp): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', new NigerianPhone]]);

        $phone = PhoneNumber::normalize($request->input('phone'));
        $exists = User::query()->where('phone', $phone)->exists();

        $this->sendOtp($request, $otp, $phone, OtpService::PURPOSE_LOGIN, deliver: $exists);
        $request->session()->put('otp.remember', $request->boolean('remember'));

        return redirect()->route('login.otp.verify');
    }

    public function show(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('otp.purpose') !== OtpService::PURPOSE_LOGIN) {
            return redirect()->route('login');
        }

        return view('auth.verify-otp', [
            'subheading' => __('If :phone has an account, a 6-digit code is on its way by SMS. It expires in :minutes minutes.', [
                'phone' => PhoneNumber::mask($request->session()->get('otp.phone')),
                'minutes' => config('napps.otp.ttl_minutes'),
            ]),
            'action' => route('login.otp.verify.store'),
            'resend' => route('login.otp.resend'),
            'back' => route('login'),
            'heading' => __('Enter your sign-in code'),
        ]);
    }

    public function verify(Request $request, OtpService $otp): RedirectResponse
    {
        $phone = $this->checkOtp($request, $otp, OtpService::PURPOSE_LOGIN);
        $remember = (bool) $request->session()->pull('otp.remember', false);

        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            throw ValidationException::withMessages(['code' => __('That code is wrong or has expired. Check the SMS or request a new code.')]);
        }

        if ($user->hasConfirmedTwoFactor()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => $remember]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request, OtpService $otp): RedirectResponse
    {
        $phone = $request->session()->get('otp.phone');

        if (! $phone || $request->session()->get('otp.purpose') !== OtpService::PURPOSE_LOGIN) {
            return redirect()->route('login');
        }

        $exists = User::query()->where('phone', $phone)->exists();
        $this->sendOtp($request, $otp, $phone, OtpService::PURPOSE_LOGIN, deliver: $exists);

        return back()->with('status', __('We sent a new code.'));
    }
}
