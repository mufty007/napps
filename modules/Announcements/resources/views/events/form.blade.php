<x-layouts.app :title="$event->exists ? __('Edit event') : __('New event')">
    <x-ui.page-header
        :title="$event->exists ? __('Edit event') : __('New event')"
        :description="__('Shown on the public Events & webinars page when published.')"
    />

    <form method="POST" action="{{ $event->exists ? route('events.update', $event) : route('events.store') }}" class="mx-auto max-w-2xl space-y-5">
        @csrf
        @if ($event->exists) @method('PUT') @endif

        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input name="title" :label="__('Title')" :value="old('title', $event->title)" required autofocus />
                <x-ui.select name="kind" :label="__('Type')" :value="old('kind', $event->kind)" :options="[
                    'event' => __('Event'),
                    'webinar' => __('Webinar'),
                    'meeting' => __('Meeting'),
                    'agm' => __('AGM'),
                ]" required />
                <x-ui.input name="summary" :label="__('Short summary')" :value="old('summary', $event->summary)" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="starts_at" type="datetime-local" :label="__('Starts')" :value="old('starts_at', $event->starts_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'))" required />
                    <x-ui.input name="ends_at" type="datetime-local" :label="__('Ends (optional)')" :value="old('ends_at', $event->ends_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'))" />
                </div>
                <x-ui.input name="venue" :label="__('Venue')" :value="old('venue', $event->venue)" />
                <x-ui.input name="online_url" type="url" :label="__('Online link (for webinars)')" :value="old('online_url', $event->online_url)" placeholder="https://" />
                <div>
                    <label for="body" class="block text-sm font-medium text-stone-800">{{ __('Details') }}</label>
                    <textarea id="body" name="body" rows="8" class="mt-1.5 block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('body', $event->body) }}</textarea>
                </div>
                <div>
                    <label for="agenda" class="block text-sm font-medium text-stone-800">{{ __('Agenda') }}</label>
                    <textarea id="agenda" name="agenda" rows="5" aria-describedby="agenda-hint" placeholder="09:00 | Registration&#10;10:00 | Opening and welcome&#10;12:30 | Lunch" class="mt-1.5 block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('agenda', $event->agendaText()) }}</textarea>
                    <p id="agenda-hint" class="mt-1 text-sm text-stone-500">{{ __('One item per line, as "time | title". The time is optional.') }}</p>
                    @error('agenda') <p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                </div>
                @if ($event->exists)
                    <div>
                        <label for="minutes" class="block text-sm font-medium text-stone-800">{{ __('Minutes') }}</label>
                        <textarea id="minutes" name="minutes" rows="8" aria-describedby="minutes-hint" class="mt-1.5 block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('minutes', $event->minutes) }}</textarea>
                        <p id="minutes-hint" class="mt-1 text-sm text-stone-500">{{ __('Add after the meeting. Shown to signed-in members on the event page.') }}</p>
                        @error('minutes') <p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                    </div>
                @endif
                <input type="hidden" name="audience" value="public">
                <input type="hidden" name="level" value="national">
            </div>
        </x-ui.card>

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" name="publish" value="1" icon-right="send">{{ __('Publish') }}</x-ui.button>
            <x-ui.button type="submit" variant="secondary">{{ __('Save draft') }}</x-ui.button>
            @if ($event->exists && $event->published_at)
                <x-ui.button type="submit" name="unpublish" value="1" variant="ghost">{{ __('Unpublish') }}</x-ui.button>
            @endif
            <x-ui.button :href="route('events.manage')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
