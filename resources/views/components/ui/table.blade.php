{{--
    <x-ui.table>
        <x-slot:head><th>Name</th>...</x-slot:head>
        <tr><td>...</td></tr>
    </x-ui.table>
    Scrolls sideways on narrow phones instead of squashing columns.
--}}
<div {{ $attributes->class('-mx-5 overflow-x-auto') }}>
    <table class="min-w-full divide-y divide-stone-200 text-left text-sm [&_td]:px-5 [&_td]:py-3 [&_th]:px-5 [&_th]:py-2.5">
        @isset($head)
            <thead class="text-xs font-semibold uppercase tracking-wide text-stone-500">
                <tr>{{ $head }}</tr>
            </thead>
        @endisset
        <tbody class="divide-y divide-stone-100 text-stone-700">
            {{ $slot }}
        </tbody>
    </table>
</div>
