@props(['title' => null, 'description' => null])

<aside {{ $attributes->class('space-y-6 rounded-2xl bg-white p-5 ring-1 ring-stone-200 sm:p-6') }}>
    @if ($title)
        <header>
            <h2 class="text-base font-semibold text-stone-900">{{ $title }}</h2>
            @if ($description)
                <p class="mt-1 text-sm text-stone-500">{{ $description }}</p>
            @endif
        </header>
    @endif
    {{ $slot }}
</aside>
