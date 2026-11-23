@props(['title' => null, 'description' => null, 'padding' => true])

<section {{ $attributes->class('rounded-2xl bg-white shadow-sm ring-1 ring-stone-200/80') }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-3 border-b border-stone-100 px-5 py-4">
            <div class="min-w-0 flex-1">
                @if ($title)
                    <h2 class="text-base font-semibold text-stone-900">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-stone-500">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
