@props(['label', 'value' => 0, 'icon' => null, 'format' => null, 'hint' => null, 'href' => null])
{{-- Dashboard number tile. format: null | 'currency' | 'percent' --}}
@php
    $modifier = $format === 'currency' ? '.currency' : '';
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class([
    'group block rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200/80',
    'transition-shadow hover:shadow-md hover:ring-stone-300' => $href,
]) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-medium text-stone-500">{{ $label }}</p>
        @if ($icon)
            <span class="flex size-9 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                <x-ui.icon :name="$icon" size="size-[18px]" />
            </span>
        @endif
    </div>
    <p class="mt-2 text-3xl font-semibold tracking-tight text-stone-900 tabular">
        @if (is_numeric($value))
            <span x-data x-count-up{{ $modifier }}="{{ $value }}">{{ $format === 'currency' ? '₦'.number_format((float) $value) : number_format((float) $value) }}</span>@if ($format === 'percent')<span class="text-xl text-stone-400">%</span>@endif
        @else
            {{ $value }}
        @endif
    </p>
    @if ($hint)
        <p class="mt-1 text-sm text-stone-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>
