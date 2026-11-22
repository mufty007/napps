@props(['tone' => 'neutral', 'dot' => false])
@php
    $tones = [
        'neutral' => 'bg-stone-100 text-stone-700 ring-stone-200',
        'brand' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'danger' => 'bg-red-50 text-red-800 ring-red-200',
        'info' => 'bg-sky-50 text-sky-800 ring-sky-200',
    ];
    $dots = [
        'neutral' => 'bg-stone-400', 'brand' => 'bg-brand-500', 'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500', 'danger' => 'bg-red-500', 'info' => 'bg-sky-500',
    ];
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '.($tones[$tone] ?? $tones['neutral'])) }}>
    @if ($dot)<span class="size-1.5 rounded-full {{ $dots[$tone] ?? $dots['neutral'] }}" aria-hidden="true"></span>@endif
    {{ $slot }}
</span>
