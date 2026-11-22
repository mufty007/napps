<x-layouts.auth :title="$heading" :heading="$heading" :subheading="$subheading">

    @if (session('otp.dev_code'))
        <x-ui.alert tone="warning" :title="__('Local development')" class="mb-6">
            {{ __('SMS is logged, not sent. Your code is') }} <strong class="tabular tracking-widest">{{ session('otp.dev_code') }}</strong>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        <x-ui.input
            name="code"
            :label="__('Code')"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="{{ config('napps.otp.length') }}"
            pattern="[0-9]*"
            class="[&_input]:text-center [&_input]:text-2xl [&_input]:tracking-[0.5em] [&_input]:tabular"
            required
            autofocus
        />
        <x-ui.button type="submit" class="w-full" size="lg">{{ __('Continue') }}</x-ui.button>
    </form>

    <div class="mt-6 flex items-center justify-between gap-4 text-sm">
        <a href="{{ $back }}" class="font-semibold text-stone-600 hover:text-stone-900">{{ __('Use a different number') }}</a>
        <form method="POST" action="{{ $resend }}">
            @csrf
            <button type="submit" class="min-h-11 font-semibold text-brand-700 hover:text-brand-800">{{ __('Send a new code') }}</button>
        </form>
    </div>
    @error('phone')
        <p class="mt-3 text-sm font-medium text-red-700">{{ $message }}</p>
    @enderror
</x-layouts.auth>
