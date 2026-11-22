<x-layouts.auth
    :title="__('Sign in')"
    :heading="__('Sign in')"
    :subheading="__('We will text a 6-digit code to your phone. No password needed.')"
>
    <div x-data="{ mode: @js($errors->has('login') || $errors->has('password') ? 'password' : 'phone') }">
        <div class="mb-6 grid grid-cols-2 gap-1 rounded-xl bg-stone-100 p-1" role="tablist">
            <button type="button" role="tab" x-on:click="mode = 'phone'" :aria-selected="mode === 'phone'"
                :class="mode === 'phone' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500'"
                class="min-h-10 rounded-lg text-sm font-semibold transition-colors">{{ __('Phone code') }}</button>
            <button type="button" role="tab" x-on:click="mode = 'password'" :aria-selected="mode === 'password'"
                :class="mode === 'password' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500'"
                class="min-h-10 rounded-lg text-sm font-semibold transition-colors">{{ __('Password') }}</button>
        </div>

        <form method="POST" action="{{ route('login.otp.send') }}" class="space-y-5" x-show="mode === 'phone'">
            @csrf
            <x-ui.input name="phone" type="tel" :label="__('Phone number')" inputmode="tel" autocomplete="tel" placeholder="0803 123 4567" required autofocus />
            <x-ui.checkbox name="remember" :label="__('Keep me signed in on this device')" />
            <x-ui.button type="submit" class="w-full" size="lg" icon-right="arrow-right">{{ __('Text me a code') }}</x-ui.button>
        </form>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-5" x-show="mode === 'password'" x-cloak>
            @csrf
            <x-ui.input name="login" :label="__('Phone number or email')" autocomplete="username" required />
            <x-ui.input name="password" type="password" :label="__('Password')" autocomplete="current-password" required />
            <div class="flex items-center justify-between gap-4">
                <x-ui.checkbox name="remember" id="remember-password" :label="__('Keep me signed in')" />
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">{{ __('Forgot password?') }}</a>
            </div>
            <x-ui.button type="submit" class="w-full" size="lg">{{ __('Sign in') }}</x-ui.button>
        </form>
    </div>

    <p class="mt-8 text-center text-sm text-stone-600">
        {{ __('New to the platform?') }}
        <a href="{{ route('register') }}" class="font-semibold text-brand-700 hover:text-brand-800">{{ __('Create an account') }}</a>
    </p>
</x-layouts.auth>
