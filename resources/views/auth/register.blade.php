<x-layouts.auth
    :title="__('Create account')"
    :heading="__('Create your account')"
    :subheading="__('For proprietors, school staff, executives and parents. We will confirm your phone number by SMS.')"
>
    <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
        @csrf
        <x-ui.input name="name" :label="__('Full name')" autocomplete="name" required autofocus />
        <x-ui.input name="phone" type="tel" :label="__('Phone number')" inputmode="tel" autocomplete="tel" placeholder="0803 123 4567" required />
        @error('phone_e164')
            <p class="-mt-3 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror
        <x-ui.input name="email" type="email" :label="__('Email (optional)')" autocomplete="email" :hint="__('Used for receipts and password reset if you add one later.')" />
        <x-ui.checkbox name="terms" :label="__('I agree to the community guidelines and to NAPPS processing my data under the NDPA 2023.')" required />
        <x-ui.button type="submit" class="w-full" size="lg" icon-right="arrow-right">{{ __('Text me a code') }}</x-ui.button>
    </form>

    <p class="mt-8 text-center text-sm text-stone-600">
        {{ __('Already registered?') }}
        <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:text-brand-800">{{ __('Sign in') }}</a>
    </p>
</x-layouts.auth>
