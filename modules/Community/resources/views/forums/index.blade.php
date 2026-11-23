<x-layouts.app :title="__('Forums')">
    <x-ui.page-header :eyebrow="__('Community')" :title="__('Forums')" :description="__('Ask and answer by topic. Answered questions stay searchable for every member.')" />

    <form method="GET" action="{{ route('community.forums.index') }}" class="mb-6 flex gap-2" role="search">
        <x-ui.input name="q" type="search" :value="$search" :label="__('Search answered questions and threads')" class="min-w-0 flex-1" :placeholder="__('e.g. instalment plan, landlord notice')" />
        <x-ui.button type="submit" variant="secondary" icon="search" class="self-end">{{ __('Search') }}</x-ui.button>
    </form>

    @if (mb_strlen($search) >= 3)
        <x-ui.card class="mb-6" :title="trans_choice(':count result|:count results', $results->count())" :padding="$results->isEmpty()">
            @if ($results->isEmpty())
                <x-ui.empty-state icon="search" :title="__('No threads match')" :description="__('Try other words, or ask it as a new question in the right forum.')" />
            @else
                <ul class="divide-y divide-stone-100">
                    @foreach ($results as $thread)
                        @include('community::forums.partials.thread-row', ['thread' => $thread, 'forum' => $thread->forum, 'showForum' => true])
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @endif

    <div class="space-y-8">
        @foreach ($spaces as $space)
            @php $list = $forums->get($space->id, collect()); @endphp
            @continue($list->isEmpty())
            <section>
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-stone-500">
                    <x-ui.icon :name="match ($space->type) { 'national' => 'flag', 'state' => 'map', default => 'map-pin' }" size="size-4" /> {{ $space->name }}
                </h2>
                <ul class="divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white ring-1 ring-stone-200">
                    @foreach ($list as $forum)
                        <li>
                            <a href="{{ route('community.forums.show', $forum) }}" class="group flex items-center gap-4 px-4 py-4 transition-colors hover:bg-stone-50 sm:px-5">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700" aria-hidden="true">
                                    <x-ui.icon :name="match (true) {
                                        str_contains($forum->slug, 'fee') => 'wallet',
                                        str_contains($forum->slug, 'curriculum') => 'book-open',
                                        str_contains($forum->slug, 'government') => 'landmark',
                                        str_contains($forum->slug, 'landlord') => 'building-2',
                                        str_contains($forum->slug, 'legal') => 'scale',
                                        str_contains($forum->slug, 'staff') => 'users',
                                        str_contains($forum->slug, 'tech') => 'cpu',
                                        default => 'messages-square',
                                    }" size="size-5" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-stone-900 group-hover:text-brand-800">{{ __($forum->name) }}</span>
                                    <span class="block truncate text-sm text-stone-500">{{ $forum->description }}</span>
                                    @if ($forum->open_questions_count)
                                        <span class="mt-1 inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 sm:hidden">
                                            <span class="size-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>{{ trans_choice(':count open question|:count open questions', $forum->open_questions_count) }}
                                        </span>
                                    @endif
                                </span>
                                @if ($forum->open_questions_count)
                                    <x-ui.badge tone="warning" dot class="hidden sm:inline-flex">{{ trans_choice(':count open question|:count open questions', $forum->open_questions_count) }}</x-ui.badge>
                                @endif
                                <span class="hidden w-20 shrink-0 text-right sm:block">
                                    <span class="block font-semibold text-stone-900 tabular">{{ $forum->threads_count }}</span>
                                    <span class="block text-xs text-stone-500">{{ trans_choice('thread|threads', $forum->threads_count) }}</span>
                                </span>
                                <x-ui.icon name="chevron-right" size="size-4" class="shrink-0 text-stone-300 group-hover:text-brand-700" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</x-layouts.app>
