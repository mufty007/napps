@php
    $example = '{'.'{school_name}'.'}';
@endphp
<x-layouts.app :title="$template->exists ? __('Edit template') : __('New template')">
    <x-ui.page-header
        :eyebrow="__('Templates')"
        :title="$template->exists ? __('Edit :name', ['name' => $template->name]) : __('New template')"
        :description="__('Write the document as HTML. Wrap each blank in double curly braces, e.g. :example, and it becomes a field members fill in.', ['example' => $example])"
    />

    <form method="POST" action="{{ $template->exists ? route('templates.update', $template) : route('templates.store') }}" class="mx-auto max-w-3xl space-y-5">
        @csrf
        @if ($template->exists) @method('PUT') @endif

        @if ($template->exists)
            <x-ui.alert tone="warning">
                {{ __('Changing the wording creates version :next, clears the legal review and unpublishes the template until it is reviewed again.', ['next' => $template->version + 1]) }}
            </x-ui.alert>
        @endif

        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input name="name" :label="__('Name')" :value="$template->name" required autofocus />

                <div class="grid gap-5 sm:grid-cols-2">
                    @if ($template->exists)
                        <div class="space-y-1.5">
                            <span class="block text-sm font-medium text-stone-800">{{ __('Slug') }}</span>
                            <p class="flex min-h-11 items-center rounded-xl bg-stone-50 px-3.5 text-sm text-stone-600 ring-1 ring-inset ring-stone-200">{{ $template->slug }}</p>
                        </div>
                    @else
                        <x-ui.input name="slug" :label="__('Slug')" :value="$template->slug" :hint="__('Lower-case letters, numbers and underscores, e.g. demand_letter.')" required />
                    @endif
                    <x-ui.select name="category" :label="__('Category')" :value="$template->category" :options="\Modules\Templates\Models\DocumentTemplate::categoryLabels()" required />
                </div>

                <x-ui.input name="description" :label="__('Short description')" :value="$template->description" />

                <div>
                    <label for="body_html" class="block text-sm font-medium text-stone-800">{{ __('Document body (HTML)') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <textarea id="body_html" name="body_html" rows="22" required spellcheck="false"
                        @error('body_html') aria-invalid="true" aria-describedby="body_html-error" @enderror
                        class="mt-1.5 block w-full rounded-xl border-0 py-2.5 font-mono text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('body_html', $template->body_html) }}</textarea>
                    @error('body_html') <p id="body_html-error" class="mt-1.5 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-sm text-stone-500">{{ __('Field names ending in _date, amount or fee become date and naira inputs. Keep fields lower-case with underscores.') }}</p>
                </div>

                @if ($fields !== [])
                    <div>
                        <p class="text-sm font-medium text-stone-800">{{ __('Fields in the saved version') }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($fields as $key => $field)
                                <x-ui.badge>{{ $field['label'] }}</x-ui.badge>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-ui.card>

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" icon="save">{{ $template->exists ? __('Save changes') : __('Save draft') }}</x-ui.button>
            <x-ui.button :href="$template->exists ? route('templates.show', $template) : route('templates.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
