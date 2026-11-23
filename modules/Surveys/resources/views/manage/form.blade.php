@php
    $editing = $survey->exists;
    $locked = $editing && ! $survey->isDraft();
@endphp
<x-layouts.app :title="$editing ? __('Edit survey') : __('New survey')">
    <x-ui.page-header
        :eyebrow="__('Surveys')"
        :title="$editing ? $survey->title : __('New survey')"
        :description="$editing ? null : __('Describe the survey and choose who receives it. You add the questions next.')"
    >
        @if ($editing)
            <x-slot:actions>
                <x-ui.badge :tone="$survey->statusTone()" dot>{{ $survey->statusLabel() }}</x-ui.badge>
                @unless ($survey->isDraft())
                    <x-ui.button :href="route('surveys.results', $survey)" variant="secondary" icon="chart-column">{{ __('Results') }}</x-ui.button>
                @endunless
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($errors->has('publish') || $errors->has('survey'))
        <x-ui.alert tone="danger" class="mb-5">{{ $errors->first('publish') ?: $errors->first('survey') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
        <form method="POST" action="{{ $editing ? route('surveys.update', $survey) : route('surveys.store') }}" class="space-y-5">
            @csrf
            @if ($editing) @method('PUT') @endif

            <x-ui.card :title="__('Details')">
                <fieldset class="space-y-5" @disabled(! $canEdit)>
                    <x-ui.input name="title" :label="__('Title')" :value="$survey->title" required maxlength="180" />
                    <div class="space-y-1.5">
                        <label for="description" class="block text-sm font-medium text-stone-800">{{ __('Introduction (optional)') }}</label>
                        <textarea id="description" name="description" rows="4" maxlength="5000" class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('description', $survey->description) }}</textarea>
                        @error('description') <p class="text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                    </div>

                    @if (! $locked)
                        <x-ui.select name="audience" :label="__('Send to')" :value="$audienceKey" :options="$audiences" :hint="__('Only active member schools in this audience see the survey.')" required />
                    @else
                        <div>
                            <p class="text-sm font-medium text-stone-800">{{ __('Send to') }}</p>
                            <p class="mt-1 text-sm text-stone-600">{{ $survey->audienceLabel() }}</p>
                        </div>
                    @endif

                    <x-ui.input name="closes_at" type="datetime-local" :label="__('Closes (optional)')" :value="$survey->closes_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i')" />
                </fieldset>
            </x-ui.card>

            @if ($canEdit)
                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit">{{ $editing ? __('Save changes') : __('Save and add questions') }}</x-ui.button>
                    <x-ui.button :href="route('surveys.index')" variant="ghost">{{ __('Back') }}</x-ui.button>
                </div>
            @endif
        </form>

        @if ($editing)
            <div class="space-y-6">
                <x-ui.card id="questions" :title="__('Questions')" :description="trans_choice(':count question|:count questions', $questions->count())">
                    @if ($questions->isEmpty())
                        <x-ui.empty-state icon="list-checks" :title="__('No questions yet')" :description="__('Add your first question below.')" />
                    @else
                        <ol class="space-y-3">
                            @foreach ($questions as $question)
                                <li class="rounded-xl bg-stone-50 p-4 ring-1 ring-stone-200">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">
                                                {{ __('Question :n', ['n' => $loop->iteration]) }} · {{ $question->typeLabel() }}
                                                @if ($question->required) · {{ __('Required') }} @endif
                                            </p>
                                            <p class="mt-1 font-medium text-stone-900">{{ $question->prompt }}</p>
                                            @if ($question->type === 'mc')
                                                <ul class="mt-2 list-inside list-disc text-sm text-stone-600">
                                                    @foreach ($question->choices() as $choice)<li>{{ $choice }}</li>@endforeach
                                                </ul>
                                            @elseif ($question->type === 'scale')
                                                @php [$min, $max] = $question->scaleRange(); @endphp
                                                <p class="mt-2 text-sm text-stone-600">
                                                    {{ $min }} @if ($question->scaleLabel('min')) ({{ $question->scaleLabel('min') }}) @endif
                                                    – {{ $max }} @if ($question->scaleLabel('max')) ({{ $question->scaleLabel('max') }}) @endif
                                                </p>
                                            @endif
                                        </div>
                                        @if ($canEdit && ! $locked)
                                            <div class="flex shrink-0 items-center gap-1">
                                                <form method="POST" action="{{ route('surveys.questions.move', [$survey, $question]) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="up">
                                                    <button type="submit" class="flex size-11 items-center justify-center rounded-xl text-stone-500 hover:bg-stone-200 disabled:opacity-30" @disabled($loop->first)>
                                                        <x-ui.icon name="arrow-up" /><span class="sr-only">{{ __('Move up') }}</span>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('surveys.questions.move', [$survey, $question]) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="down">
                                                    <button type="submit" class="flex size-11 items-center justify-center rounded-xl text-stone-500 hover:bg-stone-200 disabled:opacity-30" @disabled($loop->last)>
                                                        <x-ui.icon name="arrow-down" /><span class="sr-only">{{ __('Move down') }}</span>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('surveys.questions.destroy', [$survey, $question]) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="flex size-11 items-center justify-center rounded-xl text-red-600 hover:bg-red-50">
                                                        <x-ui.icon name="trash-2" /><span class="sr-only">{{ __('Remove question') }}</span>
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-ui.card>

                @if ($canEdit && ! $locked)
                    <x-ui.card :title="__('Add a question')" x-data="{ type: @js(old('type', 'mc')) }">
                        <form method="POST" action="{{ route('surveys.questions.store', $survey) }}" class="space-y-5">
                            @csrf
                            <x-ui.select name="type" :label="__('Type')" :options="\Modules\Surveys\Models\SurveyQuestion::typeLabels()" :value="old('type', 'mc')" x-model="type" />
                            <x-ui.input name="prompt" :label="__('Question')" maxlength="500" required />

                            <div x-show="type === 'mc'" class="space-y-1.5">
                                <label for="choices" class="block text-sm font-medium text-stone-800">{{ __('Choices') }}</label>
                                <textarea id="choices" name="choices" rows="5" placeholder="{{ __('One choice per line') }}" class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('choices') }}</textarea>
                                @error('choices') <p class="text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div x-show="type === 'scale'" x-cloak class="grid gap-5 sm:grid-cols-2">
                                <x-ui.select name="scale_min" :label="__('Starts at')" :options="['1' => '1', '0' => '0']" :value="old('scale_min', '1')" />
                                <x-ui.select name="scale_max" :label="__('Ends at')" :options="['5' => '5', '7' => '7', '10' => '10']" :value="old('scale_max', '5')" />
                                <x-ui.input name="min_label" :label="__('Low end label')" :placeholder="__('e.g. Not at all')" maxlength="60" />
                                <x-ui.input name="max_label" :label="__('High end label')" :placeholder="__('e.g. Very much')" maxlength="60" />
                            </div>

                            <input type="hidden" name="required" value="0">
                            <x-ui.checkbox name="required" :label="__('Required')" :checked="true" />

                            <x-ui.button type="submit" icon="plus">{{ __('Add question') }}</x-ui.button>
                        </form>
                    </x-ui.card>
                @endif

                @if ($canEdit)
                    <x-ui.card :title="__('Publishing')">
                        <div class="flex flex-wrap gap-3">
                            @if ($survey->isOpen())
                                <form method="POST" action="{{ route('surveys.close', $survey) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" icon="lock">{{ __('Close survey') }}</x-ui.button>
                                </form>
                                <p class="self-center text-sm text-stone-500">{{ __('Schools can answer until you close it.') }}</p>
                            @else
                                <form method="POST" action="{{ route('surveys.publish', $survey) }}">
                                    @csrf
                                    <x-ui.button type="submit" icon-right="send">{{ $survey->isDraft() ? __('Publish survey') : __('Reopen survey') }}</x-ui.button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('surveys.destroy', $survey) }}" onsubmit="return confirm(@js(__('Delete this survey?')))">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" variant="ghost" icon="trash-2" class="text-red-700">{{ __('Delete') }}</x-ui.button>
                            </form>
                        </div>
                    </x-ui.card>
                @endif
            </div>
        @endif
    </div>
</x-layouts.app>
