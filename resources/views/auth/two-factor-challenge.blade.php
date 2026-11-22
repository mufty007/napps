<x-layouts.auth
    :title="__('Two-factor check')"
    :heading="__('One more step')"
    :subheading="__('Open your authenticator app and enter the 6-digit code for :app.', ['app' => config('napps.name')])"
>
    <div x-data="{ recovery: @js($errors->has('recovery_code')) }">
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-5">
            @csrf
            <div x-show="! recovery">
                <x-ui.input name="code" :label="__('Authenticator code')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" x-bind:disabled="recovery" autofocus />
            </div>
            <div x-show="recovery" x-cloak>
                <x-ui.input name="recovery_code" :label="__('Recovery code')" autocomplete="off" x-bind:disabled="! recovery" />
            </div>
            <x-ui.button type="submit" class="w-full" size="lg">{{ __('Verify') }}</x-ui.button>
        </form>

        <button type="button" class="mt-6 min-h-11 text-sm font-semibold text-brand-700 hover:text-brand-800" x-on:click="recovery = ! recovery">
            <span x-show="! recovery">{{ __('Lost your phone? Use a recovery code') }}</span>
            <span x-show="recovery" x-cloak>{{ __('Use an authenticator code') }}</span>
        </button>
    </div>
</x-layouts.auth>
