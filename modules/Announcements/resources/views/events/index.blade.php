<x-layouts.public :title="__('Events & webinars')">
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
        <x-ui.page-header
            :eyebrow="__('Gather')"
            :title="__('Events & webinars')"
            :description="__('Meetings, training webinars and AGMs across national, state and chapter levels.')"
        />

        <div class="mt-6 flex flex-wrap gap-2">
            @foreach (['' => __('All'), 'event' => __('Events'), 'webinar' => __('Webinars'), 'meeting' => __('Meetings'), 'agm' => __('AGMs')] as $value => $label)
                <a href="{{ route('events.index', array_filter(['kind' => $value])) }}"
                   @class([
                       'inline-flex min-h-11 items-center rounded-full px-4 text-sm font-semibold ring-1',
                       'bg-brand-700 text-white ring-brand-700' => $kind === $value,
                       'bg-white text-stone-700 ring-stone-200 hover:bg-stone-50' => $kind !== $value,
                   ])>{{ $label }}</a>
            @endforeach
        </div>

        @if ($events->isEmpty())
            <div class="mt-10">
                <x-ui.empty-state icon="calendar-days" :title="__('No upcoming events')" :description="__('When chapters and national post meetings or webinars, they will show here.')" />
            </div>
        @else
            <div class="mt-10 space-y-4">
                @foreach ($events as $event)
                    <a href="{{ route('events.show', $event) }}" class="flex flex-col gap-3 rounded-2xl bg-white p-5 ring-1 ring-stone-200 transition hover:ring-brand-300 sm:flex-row sm:items-center sm:justify-between" x-reveal>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.badge tone="brand">{{ $event->kindLabel() }}</x-ui.badge>
                                @if ($event->isOnline())
                                    <x-ui.badge>{{ __('Online') }}</x-ui.badge>
                                @endif
                            </div>
                            <h2 class="mt-2 text-lg font-semibold text-stone-900">{{ $event->title }}</h2>
                            <p class="mt-1 text-sm text-stone-600">{{ $event->summary ?: ($event->venue ?: __('Details inside')) }}</p>
                        </div>
                        <div class="shrink-0 text-sm font-semibold text-brand-800 sm:text-right">
                            <p>{{ $event->starts_at->timezone(config('app.timezone'))->format('D, j M Y') }}</p>
                            <p class="font-medium text-stone-500">{{ $event->starts_at->timezone(config('app.timezone'))->format('g:ia') }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-10">{{ $events->links() }}</div>
        @endif
    </section>
</x-layouts.public>
