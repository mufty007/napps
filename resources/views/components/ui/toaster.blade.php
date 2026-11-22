{{-- Renders Alpine.store('toasts') and turns session flashes into toasts. --}}
@php
    // Fortify reports outcomes as status slugs; give them words.
    $fortify = [
        'two-factor-authentication-enabled' => __('Scan the QR code to finish turning on two-factor authentication.'),
        'two-factor-authentication-confirmed' => __('Two-factor authentication is on.'),
        'two-factor-authentication-disabled' => __('Two-factor authentication is off.'),
        'recovery-codes-generated' => __('New recovery codes generated.'),
        'profile-information-updated' => __('Profile saved.'),
        'password-updated' => __('Password saved.'),
    ];
    $status = session('status');

    $flashes = collect([
        'success' => is_string($status) ? ($fortify[$status] ?? $status) : null,
        'warning' => session('warning'),
        'danger' => session('error'),
    ])->filter(fn ($message) => is_string($message) && $message !== '');
@endphp

<div
    x-data
    x-init="@foreach ($flashes as $tone => $message) $store.toasts.push(@js($message), @js($tone)); @endforeach"
    class="pointer-events-none fixed inset-x-0 top-3 z-[60] flex flex-col items-center gap-2 px-4 sm:top-auto sm:bottom-6 sm:right-6 sm:left-auto sm:items-end"
    aria-live="polite"
>
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div
            x-init="$pop($el)"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl bg-stone-900 px-4 py-3 text-sm text-white shadow-lg"
            role="status"
        >
            <span
                class="mt-1 size-2 shrink-0 rounded-full"
                :class="{
                    'bg-emerald-400': toast.tone === 'success',
                    'bg-amber-400': toast.tone === 'warning',
                    'bg-red-400': toast.tone === 'danger',
                    'bg-sky-400': ! ['success', 'warning', 'danger'].includes(toast.tone),
                }"
            ></span>
            <p class="min-w-0 flex-1" x-text="toast.message"></p>
            <button type="button" class="-my-1 -mr-1 rounded-lg p-1 text-stone-400 hover:text-white" x-on:click="$store.toasts.dismiss(toast.id)">
                <x-ui.icon name="x" size="size-4" />
                <span class="sr-only">{{ __('Dismiss') }}</span>
            </button>
        </div>
    </template>
</div>
