@props(['align' => 'right', 'width' => 'w-64'])
{{-- <x-ui.dropdown><x-slot:trigger>...</x-slot:trigger> ...items... </x-ui.dropdown> --}}
<div x-data="{ open: false }" x-on:keydown.escape="open = false" x-on:click.outside="open = false" {{ $attributes->class('relative') }}>
    <div x-on:click="open = ! open">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition duration-150 ease-snappy"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition duration-100 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @class([
            'absolute z-40 mt-2 overflow-hidden rounded-2xl bg-white p-1.5 shadow-lg ring-1 ring-stone-200',
            $width,
            'right-0 origin-top-right' => $align === 'right',
            'left-0 origin-top-left' => $align === 'left',
        ])
    >
        {{ $slot }}
    </div>
</div>
