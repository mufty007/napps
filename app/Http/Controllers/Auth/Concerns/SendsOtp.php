<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Shared OTP plumbing for sign-in, registration and identity confirmation.
 *
 * Session keys: otp.phone, otp.purpose (and otp.dev_code in local).
 */
trait SendsOtp
{
    protected function sendOtp(Request $request, OtpService $otp, string $phone, string $purpose, bool $deliver = true): void
    {
        $key = 'otp-send:'.$purpose.':'.$phone;

        if (RateLimiter::tooManyAttempts($key, (int) config('napps.otp.send_limit'))) {
            throw ValidationException::withMessages([
                'phone' => __('Too many codes requested. Try again in :minutes minutes.', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        RateLimiter::hit($key, 600);

        $request->session()->put(['otp.phone' => $phone, 'otp.purpose' => $purpose]);

        // Unknown numbers on sign-in get the same response, but no SMS, so the
        // form doesn't reveal who is registered.
        if (! $deliver) {
            return;
        }

        $code = $otp->issue($phone, $purpose, $request->ip());

        if ($this->revealsCodes()) {
            $request->session()->flash('otp.dev_code', $code);
        }
    }

    protected function checkOtp(Request $request, OtpService $otp, string $purpose): string
    {
        $request->validate(['code' => ['required', 'digits:'.config('napps.otp.length')]]);

        $phone = (string) $request->session()->get('otp.phone');

        if (! $phone || $request->session()->get('otp.purpose') !== $purpose || ! $otp->verify($phone, $purpose, (string) $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => __('That code is wrong or has expired. Check the SMS or request a new code.'),
            ]);
        }

        $request->session()->forget(['otp.phone', 'otp.purpose']);

        return $phone;
    }

    private function revealsCodes(): bool
    {
        return app()->isLocal()
            && config('napps.sms.driver') === 'log'
            && config('napps.otp.reveal_in_local');
    }
}
