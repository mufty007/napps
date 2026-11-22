@props(['title', 'description' => null, 'eyebrow' => null])

<div {{ $attributes->class('mb-6 flex flex-wrap items-end justify-between gap-4') }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-xs font-semibold uppercase tracking-wider text-brand-700">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-stone-900 sm:text-[1.75rem]">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-2xl text-sm text-stone-500">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
