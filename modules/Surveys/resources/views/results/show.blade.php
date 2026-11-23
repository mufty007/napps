<x-layouts.app :title="__('Results · :title', ['title' => $survey->title])">
    <x-ui.page-header :eyebrow="__('Survey results')" :title="$survey->title" :description="$survey->audienceLabel()">
        <x-slot:actions>
            <x-ui.badge :tone="$survey->statusTone()" dot>{{ $survey->statusLabel() }}</x-ui.badge>
            <x-ui.button :href="route('surveys.results.export', array_filter(['survey' => $survey, 'state_id' => $stateId, 'chapter_id' => $chapterId]))" variant="secondary" icon="download">{{ __('Export CSV') }}</x-ui.button>
            <x-ui.button :href="route('surveys.index')" variant="ghost">{{ __('All surveys') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar :action="route('surveys.results', $survey)" class="mb-6">
        <x-ui.select name="state_id" :label="__('State')" :options="$stateOptions" :placeholder="__('All states')" :value="$stateId" class="w-full sm:w-52" />
        <x-ui.select name="chapter_id" :label="__('Chapter')" :options="$chapterOptions" :placeholder="__('All chapters')" :value="$chapterId" class="w-full sm:w-60" />
        <x-ui.button type="submit" variant="secondary">{{ __('Filter') }}</x-ui.button>
        @if ($stateId || $chapterId)
            <x-ui.button :href="route('surveys.results', $survey)" variant="ghost">{{ __('Clear') }}</x-ui.button>
        @endif
    </x-ui.filter-bar>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat :label="__('Schools addressed')" :value="$coverage['schools']" icon="school" />
        <x-ui.stat :label="__('Responses')" :value="$coverage['responded']" icon="check-check" />
        <x-ui.stat :label="__('Response rate')" :value="$coverage['schools'] > 0 ? (int) round($coverage['responded'] / $coverage['schools'] * 100) : 0" format="percent" icon="chart-column" />
    </div>

    <div class="mb-6 grid gap-6 xl:grid-cols-2">
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ __('By chapter') }}</span></x-slot:toolbar>
            <x-slot:head>
                <th>{{ __('Chapter') }}</th>
                <th>{{ __('State') }}</th>
                <th class="text-right">{{ __('Schools') }}</th>
                <th class="text-right">{{ __('Responded') }}</th>
                <th class="text-right">{{ __('Rate') }}</th>
            </x-slot:head>
            @forelse ($coverage['chapters'] as $row)
                <tr>
                    <td class="font-semibold text-stone-900">{{ $row['chapter'] }}</td>
                    <td>{{ $row['state'] }}</td>
                    <td class="text-right tabular">{{ $row['schools'] }}</td>
                    <td class="text-right tabular">{{ $row['responded'] }}</td>
                    <td class="text-right tabular">{{ $row['rate'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-stone-500">{{ __('No schools in this audience yet.') }}</td></tr>
            @endforelse
        </x-ui.table>

        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ __('By state') }}</span></x-slot:toolbar>
            <x-slot:head>
                <th>{{ __('State') }}</th>
                <th class="text-right">{{ __('Schools') }}</th>
                <th class="text-right">{{ __('Responded') }}</th>
                <th class="text-right">{{ __('Rate') }}</th>
            </x-slot:head>
            @forelse ($coverage['states'] as $row)
                <tr>
                    <td class="font-semibold text-stone-900">{{ $row['state'] }}</td>
                    <td class="text-right tabular">{{ $row['schools'] }}</td>
                    <td class="text-right tabular">{{ $row['responded'] }}</td>
                    <td class="text-right tabular">{{ $row['rate'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-stone-500">{{ __('No schools in this audience yet.') }}</td></tr>
            @endforelse
        </x-ui.table>
    </div>

    <div class="space-y-4">
        @foreach ($questions as $question)
            @php $summary = $summaries[$question->id]; @endphp
            <x-ui.card>
                <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ __('Question :n', ['n' => $loop->iteration]) }} · {{ $question->typeLabel() }}</p>
                <h2 class="mt-1 text-base font-semibold text-stone-900">{{ $question->prompt }}</h2>
                <p class="mt-1 text-sm text-stone-500">{{ trans_choice(':count answer|:count answers', $summary['count']) }}</p>

                <div class="mt-4">
                    @if (in_array($question->type, ['mc', 'scale'], true))
                        @if ($question->type === 'scale' && $summary['average'] !== null)
                            <p class="mb-3 text-sm text-stone-700">{{ __('Average') }}: <span class="font-semibold tabular">{{ $summary['average'] }}</span></p>
                        @endif
                        <ul class="space-y-2.5">
                            @foreach ($summary['rows'] as $row)
                                <li>
                                    <div class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="text-stone-800">{{ $row['label'] }}</span>
                                        <span class="tabular text-stone-500">{{ $row['count'] }} · {{ $row['percent'] }}%</span>
                                    </div>
                                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-stone-100" role="img" aria-label="{{ $row['label'] }}: {{ $row['percent'] }}%">
                                        <div class="h-full rounded-full bg-brand-600" style="width: {{ $row['percent'] }}%"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($question->type === 'number')
                        @if ($summary['count'] > 0)
                            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                @foreach ([__('Average') => $summary['average'], __('Lowest') => $summary['min'], __('Highest') => $summary['max'], __('Total') => $summary['sum']] as $label => $number)
                                    <div class="rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200">
                                        <dt class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ $label }}</dt>
                                        <dd class="mt-1 text-lg font-semibold text-stone-900 tabular">{{ number_format((float) $number, 2) }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    @else
                        @if ($summary['answers'] === [])
                            <p class="text-sm text-stone-500">{{ __('No written answers yet.') }}</p>
                        @else
                            <ul class="space-y-2">
                                @foreach ($summary['answers'] as $text)
                                    <li class="whitespace-pre-wrap rounded-xl bg-stone-50 px-4 py-3 text-sm text-stone-800 ring-1 ring-stone-200">{{ $text }}</li>
                                @endforeach
                            </ul>
                            @if ($summary['count'] > count($summary['answers']))
                                <p class="mt-2 text-xs text-stone-500">{{ __('Showing the latest :n. Export the CSV for every answer.', ['n' => count($summary['answers'])]) }}</p>
                            @endif
                        @endif
                    @endif
                </div>
            </x-ui.card>
        @endforeach
    </div>
</x-layouts.app>
