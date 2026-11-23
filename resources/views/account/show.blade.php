<x-layouts.app :title="__('Account & security')">
    <x-ui.page-header :title="__('Account & security')" :description="__('Your profile, roles, password and two-factor authentication.')" />

    <div class="space-y-6">
        @if ($twoFactorRequired && ! $user->hasConfirmedTwoFactor())
            <x-ui.alert tone="warning" :title="__('Two-factor authentication required')">
                {{ __('Executives, bursars and secretariat staff must protect their accounts with an authenticator app. Turn it on below to open your dashboard.') }}
            </x-ui.alert>
        @endif

        <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
            {{-- Profile --}}
            <x-ui.card :title="__('Profile')" :description="__('Your phone number is how you sign in.')">
                <form method="POST" action="{{ route('user-profile-information.update') }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    <x-ui.input name="name" :label="__('Full name')" :value="$user->name" bag="updateProfileInformation" autocomplete="name" required />
                    <x-ui.input name="phone_display" :label="__('Phone number')" :value="\App\Support\PhoneNumber::format($user->phone)" disabled />
                    <x-ui.input name="email" type="email" :label="__('Email (optional)')" :value="$user->email" bag="updateProfileInformation" autocomplete="email" />
                    <x-ui.button type="submit">{{ __('Save profile') }}</x-ui.button>
                </form>
            </x-ui.card>

            {{-- Roles --}}
            <x-ui.card :title="__('Your roles')" :description="__('Switch between them from the top bar.')" :padding="$assignments->isEmpty()">
                @if ($assignments->isEmpty())
                    <x-ui.empty-state icon="user-round-check" :title="__('No roles yet')" :description="__('A proprietor, executive or the secretariat adds roles to your account.')" class="py-4" />
                @else
                    <ul class="divide-y divide-stone-100">
                        @foreach ($assignments as $assignment)
                            <li class="flex items-center justify-between gap-4 px-5 py-3.5">
                                <span class="min-w-0 truncate text-sm font-semibold text-stone-900">{{ $assignment->label() }}</span>
                                @if ($assignment->roleKey()?->requiresTwoFactor())
                                    <x-ui.badge tone="info">{{ __('Needs 2FA') }}</x-ui.badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            {{-- Two-factor --}}
            <x-ui.card :title="__('Two-factor authentication')" :description="__('A code from an authenticator app (Google Authenticator, Authy, Microsoft Authenticator) at every sign-in.')">
                <x-slot:actions>
                    @if ($user->hasConfirmedTwoFactor())
                        <x-ui.badge tone="success" dot>{{ __('On') }}</x-ui.badge>
                    @else
                        <x-ui.badge dot>{{ __('Off') }}</x-ui.badge>
                    @endif
                </x-slot:actions>

                @if ($twoFactorPending)
                    <ol class="space-y-5 text-sm text-stone-700">
                        <li>
                            <p class="font-semibold text-stone-900">{{ __('1. Scan this QR code with your authenticator app') }}</p>
                            <div class="mt-3 inline-block rounded-2xl bg-white p-3 ring-1 ring-stone-200 [&_svg]:size-44">{!! $user->twoFactorQrCodeSvg() !!}</div>
                            <p class="mt-2 text-stone-500">{{ __('Or type this key:') }} <code class="break-all rounded bg-stone-100 px-1.5 py-0.5 font-mono text-xs text-stone-800">{{ decrypt($user->two_factor_secret) }}</code></p>
                        </li>
                        <li>
                            <p class="font-semibold text-stone-900">{{ __('2. Enter the 6-digit code it shows') }}</p>
                            <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-3 flex flex-wrap items-start gap-3">
                                @csrf
                                <x-ui.input name="code" bag="confirmTwoFactorAuthentication" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="w-40" :aria-label="__('Authenticator code')" required />
                                <x-ui.button type="submit">{{ __('Turn on') }}</x-ui.button>
                            </form>
                        </li>
                    </ol>
                @elseif ($user->hasConfirmedTwoFactor())
                    @if (in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated']))
                        <x-ui.alert tone="warning" :title="__('Save your recovery codes')" class="mb-4">
                            {{ __('Each code works once if you lose your phone. Store them somewhere safe; they will not be shown again.') }}
                        </x-ui.alert>
                        <ul class="grid grid-cols-2 gap-2 rounded-xl bg-stone-50 p-4 font-mono text-sm text-stone-800">
                            @foreach ($user->recoveryCodes() as $code)
                                <li>{{ $code }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" size="sm" icon="refresh-cw">{{ __('New recovery codes') }}</x-ui.button>
                        </form>
                        @unless ($twoFactorRequired)
                            <form method="POST" action="{{ route('two-factor.disable') }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="ghost" size="sm">{{ __('Turn off') }}</x-ui.button>
                            </form>
                        @endunless
                    </div>
                @else
                    <form method="POST" action="{{ route('two-factor.enable') }}">
                        @csrf
                        <x-ui.button type="submit" icon="shield-check">{{ __('Set up two-factor') }}</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            {{-- Password --}}
            <x-ui.card :title="$user->hasPassword() ? __('Change password') : __('Add a password')" :description="__('Optional. Lets you sign in without an SMS code.')">
                <form method="POST" action="{{ route('user-password.update') }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    @if ($user->hasPassword())
                        <x-ui.input name="current_password" type="password" :label="__('Current password')" bag="updatePassword" autocomplete="current-password" required />
                    @endif
                    <x-ui.input name="password" type="password" :label="__('New password')" bag="updatePassword" autocomplete="new-password" required />
                    <x-ui.input name="password_confirmation" type="password" :label="__('Confirm new password')" bag="updatePassword" autocomplete="new-password" required />
                    <x-ui.button type="submit">{{ __('Save password') }}</x-ui.button>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
