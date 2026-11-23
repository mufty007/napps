<x-layouts.app :title="__('Manage events')">
    <x-ui.page-header :title="__('Events & webinars')" :description="__('Schedule meetings, webinars and AGMs.')">
        <x-slot:actions>
            <x-ui.button :href="route('events.create')" icon="plus">{{ __('New event') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($events->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="calendar-days" :title="__('No events yet')" :description="__('Add the first meeting or webinar.')" />
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count event|:count events', $events->total()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th>{{ __('Title') }}</th>
                <th>{{ __('When') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </x-slot:head>
            @foreach ($events as $event)
                <tr>
                    <td>
                        <p class="font-semibold text-stone-900">{{ $event->title }}</p>
                        <p class="text-xs text-stone-500">{{ $event->kindLabel() }}</p>
                    </td>
                    <td class="tabular">{{ $event->starts_at->timezone(config('app.timezone'))->format('j M Y, g:ia') }}</td>
                    <td>
                        @if ($event->published_at)
                            <x-ui.badge tone="success">{{ __('Published') }}</x-ui.badge>
                        @else
                            <x-ui.badge>{{ __('Draft') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ route('events.attendance', $event) }}" class="inline-flex min-h-11 items-center px-2 font-semibold text-brand-700 hover:text-brand-800">{{ __('Attendance') }}</a>
                        <a href="{{ route('events.edit', $event) }}" class="inline-flex min-h-11 items-center px-2 font-semibold text-brand-700 hover:text-brand-800">{{ __('Edit') }}</a>
                    </td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $events->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>
