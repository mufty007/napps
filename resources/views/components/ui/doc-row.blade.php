@props([
    'title',
    'status' => 'missing', // ready | missing | optional
    'meta' => null,
    'href' => null,
])

@php
    $tones = [
        'ready' => ['tone' => 'success', 'label' => __('Ready')],
        'missing' => ['tone' => 'warning', 'label' => __('Missing')],
        'optional' => ['tone' => 'neutral', 'label' => __('Optional')],
    ];
    $badge = $tones[$status] ?? $tones['missing'];
@endphp

<div {{ $attributes->class('flex min-h-14 items-center gap-3 border-b border-stone-100 py-3 last:border-0') }}>
    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-stone-50 text-stone-600 ring-1 ring-stone-200">
        <x-ui.icon name="file-text" size="size-4" />
    </span>
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-stone-900">{{ $title }}</p>
        @if ($meta)
            <p class="truncate text-xs text-stone-500">{{ $meta }}</p>
        @endif
    </div>
    <x-ui.badge :tone="$badge['tone']" dot>{{ $badge['label'] }}</x-ui.badge>
    @if ($href)
        <a href="{{ $href }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-700 hover:text-brand-800">{{ __('Open') }}</a>
    @endif
    {{ $slot }}
</div>
