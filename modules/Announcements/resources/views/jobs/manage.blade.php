<x-layouts.app :title="__('Manage jobs')">
    <x-ui.page-header :title="__('Job board')" :description="__('Post vacancies for member schools.')">
        <x-slot:actions>
            <x-ui.button :href="route('jobs.create')" icon="plus">{{ __('Post a job') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($jobs->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="briefcase" :title="__('No listings yet')" :description="__('Post the first vacancy on the board.')" />
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count listing|:count listings', $jobs->total()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th>{{ __('Title') }}</th>
                <th>{{ __('School') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </x-slot:head>
            @foreach ($jobs as $job)
                <tr>
                    <td class="font-semibold text-stone-900">{{ $job->title }}</td>
                    <td>{{ $job->employerName() }}</td>
                    <td>
                        @if ($job->published_at)
                            <x-ui.badge tone="success">{{ __('Published') }}</x-ui.badge>
                        @else
                            <x-ui.badge>{{ __('Draft') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <a href="{{ route('jobs.edit', $job) }}" class="inline-flex min-h-11 items-center font-semibold text-brand-700 hover:text-brand-800">{{ __('Edit') }}</a>
                    </td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $jobs->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>
