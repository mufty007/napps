@php
    $link = fn (string $status) => route('leases.disputes.index', ['status' => $status === 'open' ? null : $status]);
@endphp

<x-layouts.app :title="__('Lease disputes')">
    <x-ui.page-header
        :eyebrow="__('Association')"
        :title="__('Lease disputes')"
        :description="__('Help schools resolve landlord problems — chapter mediation first, then the legal panel.')"
    />

    <x-ui.status-tabs
        class="mb-4"
        :active="$status"
        :tabs="[
            'open' => ['label' => __('Open'), 'count' => $counts['open'], 'href' => $link('open')],
            'resolved' => ['label' => __('Resolved'), 'count' => $counts['resolved'], 'href' => $link('resolved')],
        ]"
    />

    @if ($disputes->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="scale"
                :title="$status === 'resolved' ? __('No resolved disputes yet') : __('No open disputes')"
                :description="$status === 'resolved' ? __('When a dispute is closed it will appear here.') : __('When a school opens a dispute with their landlord, it appears here for the chapter and legal panel.')"
            />
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar>
                <span class="font-semibold text-stone-900">{{ trans_choice(':count dispute|:count disputes', $disputes->total()) }}</span>
            </x-slot:toolbar>
            <x-slot:head>
                <th scope="col">{{ __('School') }}</th>
                <th scope="col" class="hidden md:table-cell">{{ __('Property') }}</th>
                <th scope="col" class="hidden lg:table-cell">{{ __('Lawyer') }}</th>
                <th scope="col" class="hidden sm:table-cell">{{ __('Opened') }}</th>
                <th scope="col">{{ __('Status') }}</th>
            </x-slot:head>
            @foreach ($disputes as $dispute)
                <tr>
                    <td class="max-w-[14rem]">
                        <a href="{{ route('leases.disputes.show', $dispute) }}" class="inline-flex min-h-11 max-w-full items-center truncate font-semibold text-brand-700 hover:text-brand-800">
                            {{ $dispute->school?->name ?? __('School') }}
                        </a>
                        <span class="block truncate text-xs text-stone-500 md:hidden">{{ \Illuminate\Support\Str::limit($dispute->lease?->property_address, 40) }}</span>
                    </td>
                    <td class="hidden max-w-[16rem] truncate md:table-cell">{{ $dispute->lease?->property_address }}</td>
                    <td class="hidden max-w-[12rem] truncate lg:table-cell">{{ $dispute->lawyer?->name ?? '—' }}</td>
                    <td class="hidden whitespace-nowrap tabular sm:table-cell">{{ $dispute->created_at->format('j M Y') }}</td>
                    <td><x-ui.badge :tone="$dispute->status->tone()" dot>{{ $dispute->status->label() }}</x-ui.badge></td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $disputes->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>
