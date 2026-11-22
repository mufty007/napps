@php $user = auth()->user(); @endphp
<x-layouts.auth
    :title="__('Confirm it is you')"
    :heading="__('Confirm it is you')"
    :subheading="__('This is a security-sensitive change, so we need to check it is really you.')"
>
    @if ($user->hasPassword())
        <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
            @csrf
            <x-ui.input name="password" type="password" :label="__('Password')" autocomplete="current-password" required autofocus />
            <x-ui.button type="submit" class="w-full" size="lg">{{ __('Confirm') }}</x-ui.button>
        </form>

        <div class="my-6 flex items-center gap-3 text-xs font-semibold uppercase tracking-wider text-stone-400">
            <span class="h-px flex-1 bg-stone-200"></span>{{ __('or') }}<span class="h-px flex-1 bg-stone-200"></span>
        </div>
    @endif

    @if (session('otp_sent') || $errors->has('code'))
        @if (session('otp.dev_code'))
            <x-ui.alert tone="warning" :title="__('Local development')" class="mb-5">
                {{ __('Your code is') }} <strong class="tabular tracking-widest">{{ session('otp.dev_code') }}</strong>
            </x-ui.alert>
        @endif
        <form method="POST" action="{{ route('identity.otp.verify') }}" class="space-y-5">
            @csrf
            <x-ui.input name="code" :label="__('Code sent to :phone', ['phone' => \App\Support\PhoneNumber::mask($user->phone)])" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus />
            <x-ui.button type="submit" class="w-full" size="lg">{{ __('Confirm with code') }}</x-ui.button>
        </form>
    @else
        <form method="POST" action="{{ route('identity.otp.send') }}">
            @csrf
            <x-ui.button type="submit" :variant="$user->hasPassword() ? 'secondary' : 'primary'" class="w-full" size="lg" icon="smartphone">
                {{ __('Text a code to :phone', ['phone' => \App\Support\PhoneNumber::mask($user->phone)]) }}
            </x-ui.button>
        </form>
        @error('phone')
            <p class="mt-3 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror
    @endif
</x-layouts.auth>
