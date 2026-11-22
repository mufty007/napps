<x-layouts.auth :title="__('Choose a new password')" :heading="__('Choose a new password')">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-ui.input name="email" type="email" :label="__('Email')" :value="$request->email" autocomplete="email" required />
        <x-ui.input name="password" type="password" :label="__('New password')" autocomplete="new-password" required />
        <x-ui.input name="password_confirmation" type="password" :label="__('Confirm new password')" autocomplete="new-password" required />
        <x-ui.button type="submit" class="w-full" size="lg">{{ __('Save password') }}</x-ui.button>
    </form>
</x-layouts.auth>
