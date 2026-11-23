<x-layouts.app :title="__('Fill in :name', ['name' => $template->name])">
    <x-ui.page-header
        :eyebrow="$template->categoryLabel()"
        :title="$template->name"
        :description="__('Fill in the details below. Anything you leave blank prints as a line you can complete by hand.')"
    />

    <form method="POST" action="{{ route('templates.fill.store', $template) }}" class="mx-auto max-w-2xl space-y-5">
        @csrf

        @unless ($template->isReviewed())
            <x-ui.alert tone="warning" :title="__('Not yet legally reviewed')">
                {{ __('Have a lawyer check this document before you rely on it.') }}
            </x-ui.alert>
        @endunless

        <x-ui.card>
            @if ($fields === [])
                <p class="text-sm text-stone-500">{{ __('This document has no fields to fill in. Download it as it is.') }}</p>
            @else
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($fields as $key => $field)
                        @php
                            $value = $defaults[$key] ?? null;
                            $error = $errors->first($key);
                        @endphp

                        @if ($field['type'] === 'textarea')
                            <div class="space-y-1.5 sm:col-span-2">
                                <label for="{{ $key }}" class="block text-sm font-medium text-stone-800">{{ $field['label'] }}</label>
                                <textarea id="{{ $key }}" name="{{ $key }}" rows="3"
                                    @if ($error) aria-invalid="true" aria-describedby="{{ $key }}-error" @endif
                                    class="block w-full rounded-xl border-0 py-2.5 px-3.5 text-base text-stone-900 shadow-sm ring-1 ring-inset focus:ring-2 focus:ring-inset sm:text-sm {{ $error ? 'ring-red-400 focus:ring-red-600' : 'ring-stone-300 focus:ring-brand-600' }}">{{ old($key, $value) }}</textarea>
                                @if ($error) <p id="{{ $key }}-error" class="text-sm font-medium text-red-700">{{ $error }}</p> @endif
                            </div>
                        @elseif ($field['type'] === 'money')
                            <x-ui.input :name="$key" :label="$field['label']" :value="$value" prefix="₦" inputmode="decimal" autocomplete="off" :hint="__('Amount in naira, e.g. 25000')" />
                        @elseif ($field['type'] === 'date')
                            <x-ui.input :name="$key" type="date" :label="$field['label']" :value="$value" />
                        @else
                            <x-ui.input :name="$key" :label="$field['label']" :value="$value" autocomplete="off" />
                        @endif
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" icon="file-down">{{ __('Download PDF') }}</x-ui.button>
            <x-ui.button :href="route('templates.show', $template)" variant="ghost">{{ __('Back to preview') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
