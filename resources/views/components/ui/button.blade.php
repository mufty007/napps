@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconRight' => null,
    'type' => 'button',
])
@php
    $sizes = [
        'sm' => 'min-h-9 px-3 text-sm gap-1.5',
        'md' => 'min-h-11 px-4 text-sm gap-2',
        'lg' => 'min-h-12 px-5 text-base gap-2',
    ];
    $variants = [
        'primary' => 'bg-brand-700 text-white shadow-sm hover:bg-brand-800 active:bg-brand-900',
        'secondary' => 'bg-white text-stone-800 shadow-sm ring-1 ring-inset ring-stone-300 hover:bg-stone-50 active:bg-stone-100',
        'ghost' => 'text-stone-700 hover:bg-stone-100 active:bg-stone-200',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-700 active:bg-red-800',
        'accent' => 'bg-accent-400 text-stone-950 shadow-sm hover:bg-accent-300 active:bg-accent-500',
    ];
    $classes = 'inline-flex items-center justify-center rounded-xl font-semibold transition-colors duration-150 select-none disabled:pointer-events-none disabled:opacity-50 '
        .($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
    $iconSize = $size === 'lg' ? 'size-5' : 'size-4';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" :size="$iconSize" />@endif
        <span>{{ $slot }}</span>
        @if ($iconRight)<x-ui.icon :name="$iconRight" :size="$iconSize" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" :size="$iconSize" />@endif
        <span>{{ $slot }}</span>
        @if ($iconRight)<x-ui.icon :name="$iconRight" :size="$iconSize" />@endif
    </button>
@endif
