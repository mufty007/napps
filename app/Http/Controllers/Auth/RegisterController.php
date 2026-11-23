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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign-up with name + phone, confirmed by SMS code. Email is optional.
 * For proprietors, staff and executives. Parents cannot self-register —
 * chapter executives enrol them. A new account has no role until a school
 * application is approved or someone invites them.
 */
class RegisterController extends Controller
{
    use SendsOtp;

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, OtpService $otp): RedirectResponse
    {
        $request->merge(['phone_e164' => PhoneNumber::normalize($request->input('phone'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', new NigerianPhone],
            'phone_e164' => ['nullable', Rule::unique('users', 'phone')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'terms' => ['accepted'],
        ], [
            'phone_e164.unique' => __('This phone number already has an account. Sign in instead.'),
        ]);

        $request->session()->put('registration', [
            'name' => $validated['name'],
            'phone' => $validated['phone_e164'],
            'email' => isset($validated['email']) ? mb_strtolower($validated['email']) : null,
        ]);

        $this->sendOtp($request, $otp, $validated['phone_e164'], OtpService::PURPOSE_REGISTER);

        return redirect()->route('register.verify');
    }

    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('registration')) {
            return redirect()->route('register');
        }

        return view('auth.verify-otp', [
            'subheading' => __('We sent a 6-digit code to :phone. It expires in :minutes minutes.', [
                'phone' => PhoneNumber::mask($request->session()->get('registration.phone')),
                'minutes' => config('napps.otp.ttl_minutes'),
            ]),
            'action' => route('register.verify.store'),
            'resend' => route('register.resend'),
            'back' => route('register'),
            'heading' => __('Confirm your phone number'),
        ]);
    }

    public function verify(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->session()->get('registration');

        if (! $data) {
            return redirect()->route('register');
        }

        $phone = $this->checkOtp($request, $otp, OtpService::PURPOSE_REGISTER);

        $user = DB::transaction(function () use ($data, $phone) {
            if (User::withTrashed()->where('phone', $phone)->exists()) {
                throw ValidationException::withMessages(['code' => __('This phone number already has an account. Sign in instead.')]);
            }

            $user = new User(['name' => $data['name'], 'phone' => $phone, 'email' => $data['email']]);
            $user->phone_verified_at = now();
            $user->save();

            return $user;
        });

        $request->session()->forget('registration');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('start')->with('status', __('Welcome to :app.', ['app' => config('napps.name')]));
    }

    public function resend(Request $request, OtpService $otp): RedirectResponse
    {
        $phone = $request->session()->get('registration.phone');

        if (! $phone) {
            return redirect()->route('register');
        }

        $this->sendOtp($request, $otp, $phone, OtpService::PURPOSE_REGISTER);

        return back()->with('status', __('We sent a new code.'));
    }
}
