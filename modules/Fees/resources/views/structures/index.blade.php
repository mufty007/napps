<x-layouts.app :title="__('Fee structures')">
    <x-ui.page-header
        :eyebrow="__('Fees')"
        :title="__('Fee structures')"
        :description="__('Price lists for each term. Pick one when you raise invoices for a class.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('fees.invoices.index')" variant="secondary" icon="arrow-left">{{ __('Invoices') }}</x-ui.button>
            @if ($canManage)
                <x-ui.button :href="route('fees.structures.create')" icon="plus">{{ __('New structure') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($structures->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="list-checks" :title="__('No fee structures yet')" :description="__('Add tuition and other charges for a term, then raise invoices from it in one go.')">
                @if ($canManage)
                    <x-ui.button :href="route('fees.structures.create')" icon="plus">{{ __('New structure') }}</x-ui.button>
                @endif
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count structure|:count structures', $structures->count()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th scope="col">{{ __('Name') }}</th>
                <th scope="col" class="hidden sm:table-cell">{{ __('Year and term') }}</th>
                <th scope="col" class="text-right">{{ __('Per child') }}</th>
                <th scope="col" class="hidden md:table-cell">{{ __('Invoices') }}</th>
                <th scope="col">{{ __('Status') }}</th>
                @if ($canManage)<th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>@endif
            </x-slot:head>
            @foreach ($structures as $structure)
                <tr>
                    <td class="max-w-[14rem]">
                        <span class="block truncate font-semibold text-stone-900">{{ $structure->name }}</span>
                        <span class="block truncate text-xs text-stone-500">{{ $structure->items->pluck('name')->implode(', ') }}</span>
                    </td>
                    <td class="hidden whitespace-nowrap sm:table-cell">{{ $structure->academic_year }}{{ $structure->term ? ' · '.$structure->term : '' }}</td>
                    <td class="whitespace-nowrap text-right font-semibold tabular text-stone-900">{{ \App\Support\Money::format($structure->totalKobo()) }}</td>
                    <td class="hidden tabular md:table-cell">{{ $structure->invoices_count }}</td>
                    <td><x-ui.badge :tone="$structure->active ? 'success' : 'neutral'" dot>{{ $structure->active ? __('Active') : __('Off') }}</x-ui.badge></td>
                    @if ($canManage)
                        <td class="text-right">
                            <x-ui.button :href="route('fees.structures.edit', $structure)" variant="ghost" size="sm" icon="pencil">{{ __('Edit') }}</x-ui.button>
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layouts.app>
