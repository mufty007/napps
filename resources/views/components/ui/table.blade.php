{{--
    <x-ui.table>
        <x-slot:toolbar>4 schools</x-slot:toolbar>          (optional: count, chips, search)
        <x-slot:head><th>Name</th>...</x-slot:head>
        <tr><td>...</td></tr>
        <x-slot:footer>{{ $rows->links() }}</x-slot:footer> (optional: pagination)
    </x-ui.table>

    A table is its own surface (hairline ring, tinted header row). Use `flush`
    inside a <x-ui.card :padding="false"> so there is no box inside a box.
    Scrolls sideways on narrow phones instead of squashing columns.
--}}
@props(['flush' => false])

<div {{ $attributes->class([
    'min-w-0',
    'overflow-hidden rounded-2xl bg-white ring-1 ring-stone-200' => ! $flush,
    'overflow-hidden rounded-b-2xl' => $flush,
]) }}>
    @isset($toolbar)
        <div class="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-stone-200 px-4 py-2.5 text-sm text-stone-600 sm:px-5">
            {{ $toolbar }}
        </div>
    @endisset

    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_th]:px-4 [&_th]:py-2.5 [&_th]:font-semibold sm:[&_td]:px-5 sm:[&_th]:px-5">
            @isset($head)
                <thead class="border-b border-stone-200 bg-stone-50 text-[0.7rem] uppercase tracking-wider whitespace-nowrap text-stone-500">
                    <tr>{{ $head }}</tr>
                </thead>
            @endisset
            <tbody class="divide-y divide-stone-100 text-stone-700 [&>tr]:transition-colors [&>tr:hover]:bg-stone-50/70">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @isset($footer)
        @if (trim($footer) !== '')
            <div class="border-t border-stone-200 px-4 py-3 sm:px-5">{{ $footer }}</div>
        @endif
    @endisset
</div>
