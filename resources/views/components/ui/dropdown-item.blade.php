@props(['href' => null, 'icon' => null, 'active' => false, 'type' => 'submit'])
@php
    $classes = 'flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-medium transition-colors '
        .($active ? 'bg-brand-50 text-brand-800' : 'text-stone-700 hover:bg-stone-100');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" size="size-4" class="text-stone-500" />@endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
        @if ($active)<x-ui.icon name="check" size="size-4" class="text-brand-700" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" size="size-4" class="text-stone-500" />@endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
        @if ($active)<x-ui.icon name="check" size="size-4" class="text-brand-700" />@endif
    </button>
@endif
