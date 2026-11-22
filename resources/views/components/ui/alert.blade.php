@props(['tone' => 'info', 'title' => null])
@php
    $styles = [
        'info' => ['bg-sky-50 ring-sky-200 text-sky-900', 'info', 'text-sky-600'],
        'success' => ['bg-emerald-50 ring-emerald-200 text-emerald-900', 'circle-check', 'text-emerald-600'],
        'warning' => ['bg-amber-50 ring-amber-200 text-amber-900', 'triangle-alert', 'text-amber-600'],
        'danger' => ['bg-red-50 ring-red-200 text-red-900', 'circle-alert', 'text-red-600'],
    ];
    [$box, $icon, $iconColor] = $styles[$tone] ?? $styles['info'];
@endphp

<div role="{{ in_array($tone, ['warning', 'danger']) ? 'alert' : 'status' }}" {{ $attributes->class('flex gap-3 rounded-xl p-4 text-sm ring-1 ring-inset '.$box) }}>
    <x-ui.icon :name="$icon" class="{{ $iconColor }} mt-0.5" />
    <div class="min-w-0 space-y-1">
        @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
        <div>{{ $slot }}</div>
    </div>
</div>
