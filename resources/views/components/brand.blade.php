@props(['compact' => false, 'inverse' => false])
{{-- Wordmark placeholder until the official NAPPS logo is supplied. --}}
<span {{ $attributes->class('inline-flex items-center gap-2.5') }}>
    <span @class([
        'flex size-9 items-center justify-center rounded-xl font-bold tracking-tight',
        'bg-brand-700 text-white' => ! $inverse,
        'bg-white text-brand-800' => $inverse,
    ])>
        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 10.5 12 5l9 5.5" />
            <path d="M5 10v8.5h14V10" />
            <path d="M10 18.5v-5h4v5" />
        </svg>
    </span>
    @unless ($compact)
        <span class="leading-tight">
            <span @class(['block text-[0.95rem] font-semibold tracking-tight', 'text-stone-900' => ! $inverse, 'text-white' => $inverse])>{{ config('napps.name') }}</span>
            <span @class(['block text-[0.7rem] font-medium', 'text-stone-500' => ! $inverse, 'text-brand-100' => $inverse])>{{ __('Member Schools') }}</span>
        </span>
    @endunless
</span>
