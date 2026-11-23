<x-layouts.app :title="__('Surveys')">
    <x-ui.page-header
        :eyebrow="__('Association')"
        :title="__('Surveys')"
        :description="__('Ask member schools about fees, staffing, safety and more, then read the results by chapter and state.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('surveys.create')" icon="plus">{{ __('New survey') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-5">{{ $errors->first() }}</x-ui.alert>
    @endif

    @if ($surveys->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="clipboard-list" :title="__('No surveys yet')" :description="__('Create the first survey and send it to a chapter, a state or every member school.')">
                <x-ui.button :href="route('surveys.create')" icon="plus">{{ __('New survey') }}</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count survey|:count surveys', $surveys->total()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th>{{ __('Survey') }}</th>
                <th>{{ __('Audience') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="text-right">{{ __('Questions') }}</th>
                <th class="text-right">{{ __('Responses') }}</th>
                <th></th>
            </x-slot:head>
            @foreach ($surveys as $survey)
                <tr>
                    <td>
                        <p class="font-semibold text-stone-900">{{ $survey->title }}</p>
                        <p class="text-xs text-stone-500">
                            {{ __('By :name', ['name' => $survey->creator->name]) }}
                            @if ($survey->closes_at) · {{ __('Closes :date', ['date' => $survey->closes_at->timezone(config('app.timezone'))->format('j M Y')]) }} @endif
                        </p>
                    </td>
                    <td>{{ $survey->audienceLabel() }}</td>
                    <td><x-ui.badge :tone="$survey->statusTone()" dot>{{ $survey->statusLabel() }}</x-ui.badge></td>
                    <td class="text-right tabular">{{ $survey->questions_count }}</td>
                    <td class="text-right tabular">{{ $survey->responses_count }}</td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ route('surveys.edit', $survey) }}" class="inline-flex min-h-11 items-center px-2 font-semibold text-brand-700 hover:text-brand-800">{{ $survey->isDraft() ? __('Build') : __('Open') }}</a>
                        @unless ($survey->isDraft())
                            <a href="{{ route('surveys.results', $survey) }}" class="inline-flex min-h-11 items-center px-2 font-semibold text-brand-700 hover:text-brand-800">{{ __('Results') }}</a>
                        @endunless
                    </td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $surveys->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>
