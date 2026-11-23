<x-layouts.app :title="__('Chapter parents')">
    <x-ui.page-header
        :eyebrow="__('Chapter')"
        :title="__('Parents')"
        :description="__('Parents cannot create their own accounts. Add them here so they can sign in with their phone and see fees for their children.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('membership.parents.create')" icon="user-plus">{{ __('Add parent') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($parents->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="users"
                :title="__('No parents yet')"
                :description="__('Add a parent by name and phone. They will sign in with that number — no public registration.')"
            >
                <x-ui.button :href="route('membership.parents.create')" icon="user-plus">{{ __('Add parent') }}</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count parent|:count parents', $parents->total()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Phone') }}</th>
                <th>{{ __('Added') }}</th>
            </x-slot:head>
            @foreach ($parents as $row)
                <tr>
                    <td class="font-semibold text-stone-900">{{ $row->user->name }}</td>
                    <td class="tabular">{{ \App\Support\PhoneNumber::format($row->user->phone) }}</td>
                    <td class="text-stone-500">
                        {{ $row->created_at->format('j M Y') }}
                        @if ($row->addedBy)
                            · {{ $row->addedBy->name }}
                        @endif
                    </td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $parents->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>
