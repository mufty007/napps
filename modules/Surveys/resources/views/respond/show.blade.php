<x-layouts.app :title="$survey->title">
    <x-ui.page-header :eyebrow="__('Survey · :school', ['school' => $school->name])" :title="$survey->title" :description="$survey->description" />

    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please check your answers')" class="mb-5">{{ $errors->first() }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('surveys.respond.store', $survey) }}" class="mx-auto max-w-2xl space-y-5">
        @csrf

        @foreach ($questions as $question)
            @php
                $field = 'answers['.$question->id.']';
                $key = 'answers.'.$question->id;
                $old = is_scalar(old($key)) ? old($key) : null;
                $error = $errors->first($key);
            @endphp
            <x-ui.card>
                <fieldset>
                    <legend class="text-base font-medium text-stone-900">
                        <span class="text-stone-400 tabular">{{ $loop->iteration }}.</span> {{ $question->prompt }}
                        @if ($question->required) <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">{{ __('Required') }}</span> @endif
                    </legend>

                    <div class="mt-3">
                        @if ($question->type === 'mc')
                            <div class="space-y-1">
                                @foreach ($question->choices() as $choice)
                                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl px-2 py-1 hover:bg-stone-50">
                                        <input type="radio" name="{{ $field }}" value="{{ $choice }}" @checked($old === $choice) @required($question->required) class="size-5 border-stone-300 text-brand-700 focus:ring-brand-600">
                                        <span class="text-sm text-stone-800">{{ $choice }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($question->type === 'scale')
                            @php [$min, $max] = $question->scaleRange(); @endphp
                            <div class="flex flex-wrap gap-2">
                                @foreach (range($min, $max) as $point)
                                    <label class="relative">
                                        <input type="radio" name="{{ $field }}" value="{{ $point }}" @checked((string) $old === (string) $point) @required($question->required) class="peer sr-only">
                                        <span class="flex size-11 cursor-pointer items-center justify-center rounded-xl text-sm font-semibold text-stone-700 ring-1 ring-inset ring-stone-300 peer-checked:bg-brand-700 peer-checked:text-white peer-checked:ring-brand-700 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-600">{{ $point }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if ($question->scaleLabel('min') || $question->scaleLabel('max'))
                                <p class="mt-2 flex justify-between gap-4 text-xs text-stone-500">
                                    <span>{{ $question->scaleLabel('min') }}</span>
                                    <span class="text-right">{{ $question->scaleLabel('max') }}</span>
                                </p>
                            @endif
                        @elseif ($question->type === 'number')
                            <input type="number" inputmode="decimal" step="any" name="{{ $field }}" value="{{ $old }}" @required($question->required)
                                aria-label="{{ $question->prompt }}"
                                class="block min-h-11 w-full max-w-xs rounded-xl border-0 bg-white px-3.5 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">
                        @else
                            <textarea name="{{ $field }}" rows="3" maxlength="2000" @required($question->required) aria-label="{{ $question->prompt }}"
                                class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ $old }}</textarea>
                        @endif
                    </div>

                    @if ($error)
                        <p class="mt-2 text-sm font-medium text-red-700">{{ $error }}</p>
                    @endif
                </fieldset>
            </x-ui.card>
        @endforeach

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" icon-right="send">{{ __('Submit answers') }}</x-ui.button>
            <x-ui.button :href="route('surveys.respond.index')" variant="ghost">{{ __('Back') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
