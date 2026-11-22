<x-layouts.auth
    :title="__('Reset password')"
    :heading="__('Reset your password')"
    :subheading="__('Enter the email on your account and we will send a reset link. Signed up with a phone only? Sign in with an SMS code instead.')"
>
    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-ui.input name="email" type="email" :label="__('Email')" autocomplete="email" required autofocus />
        <x-ui.button type="submit" class="w-full" size="lg">{{ __('Email me a reset link') }}</x-ui.button>
    </form>

    <p class="mt-8 text-center text-sm">
        <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:text-brand-800">{{ __('Back to sign in') }}</a>
    </p>
</x-layouts.auth>
