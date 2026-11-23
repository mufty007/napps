<x-layouts.app :title="__('Surveys')">
    <x-ui.page-header
        :eyebrow="$school->name"
        :title="__('Surveys')"
        :description="__('Short surveys from your chapter, state and the national secretariat. One answer sheet per school.')"
    />

    @if ($surveys->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="clipboard-list" :title="__('No open surveys')" :description="__('When your chapter or the secretariat sends a survey, it will appear here.')" />
        </x-ui.card>
    @else
        <ul class="grid gap-4 sm:grid-cols-2">
            @foreach ($surveys as $survey)
                @php $done = $answered->has($survey->id); @endphp
                <li>
                    <x-ui.card class="flex h-full flex-col">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge tone="brand">{{ $survey->audienceLabel() }}</x-ui.badge>
                            @if ($done)
                                <x-ui.badge tone="success" dot>{{ __('Answered') }}</x-ui.badge>
                            @else
                                <x-ui.badge tone="warning" dot>{{ __('Waiting for you') }}</x-ui.badge>
                            @endif
                        </div>
                        <h2 class="mt-3 text-base font-semibold text-stone-900">{{ $survey->title }}</h2>
                        @if ($survey->description)
                            <p class="mt-1 flex-1 text-sm text-stone-500">{{ \Illuminate\Support\Str::limit($survey->description, 140) }}</p>
                        @else
                            <span class="flex-1"></span>
                        @endif
                        <p class="mt-3 text-xs text-stone-500">
                            {{ trans_choice(':count question|:count questions', $survey->questions_count) }}
                            @if ($survey->closes_at) · {{ __('Closes :date', ['date' => $survey->closes_at->timezone(config('app.timezone'))->format('j M Y')]) }} @endif
                        </p>
                        @unless ($done)
                            <x-ui.button :href="route('surveys.respond.show', $survey)" class="mt-4 self-start" icon-right="arrow-right">{{ __('Answer survey') }}</x-ui.button>
                        @else
                            <p class="mt-4 text-sm text-stone-500">{{ __('Submitted :date', ['date' => $answered[$survey->id]->timezone(config('app.timezone'))->format('j M Y')]) }}</p>
                        @endunless
                    </x-ui.card>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
