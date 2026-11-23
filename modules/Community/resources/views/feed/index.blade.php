<x-layouts.app :title="$spaceFilter?->name ?? __('Home')">
    @php $me = auth()->user(); @endphp

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_19rem]">
        {{-- Feed column --}}
        <div class="mx-auto w-full min-w-0 max-w-2xl space-y-4">
            @if ($spaceFilter)
                <div class="flex items-center gap-3">
                    <a href="{{ route('community.feed') }}" class="flex size-11 shrink-0 items-center justify-center rounded-xl text-stone-600 hover:bg-stone-100"><x-ui.icon name="arrow-left" /><span class="sr-only">{{ __('Back to Home') }}</span></a>
                    <div class="min-w-0">
                        <h1 class="truncate text-xl font-semibold tracking-tight text-stone-900">{{ $spaceFilter->name }}</h1>
                        <p class="truncate text-sm text-stone-500">{{ $spaceFilter->typeLabel() }} · {{ $spaceFilter->description }}</p>
                    </div>
                </div>
            @else
                <h1 class="sr-only">{{ __('Home') }}</h1>
            @endif

            @if ($postable->isNotEmpty())
                @php
                    $defaultSpace = (int) old('space_id', $spaceFilter?->id ?? $postable->firstWhere('type', 'chapter')?->id ?? $postable->first()->id);
                    $firstName = Str::before($me->name, ' ');
                @endphp
                <section class="rounded-2xl bg-white p-4 ring-1 ring-stone-200" x-data="{
                    open: @js($errors->any()),
                    type: @js(old('type', 'text')),
                    space: {{ $defaultSpace }},
                    formalSpaces: @js($formalSpaceIds),
                    formal: @js((bool) old('formal')),
                    options: @js(array_pad(array_values((array) old('options', [])), 2, '')),
                }">
                    <form method="POST" action="{{ route('community.feed.store') }}"
                        x-on:change="if ($event.target.name === 'type') type = $event.target.value; if ($event.target.name === 'space_id') { space = Number($event.target.value); if (! formalSpaces.includes(space)) formal = false }"
                        @submit.prevent="
                            if ($data.busy) return;
                            $data.busy = true;
                            fetch($el.action, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'X-Requested-With': 'XMLHttpRequest' },
                                body: new FormData($el),
                            }).then(async (r) => {
                                const data = await r.json().catch(() => ({}));
                                if (! r.ok) { $el.submit(); return; }
                                const feed = document.querySelector('[data-feed-list]');
                                if (feed && data.html) feed.insertAdjacentHTML('afterbegin', data.html);
                                window.toast?.(data.status);
                                $el.reset(); open = false; type = 'text'; options = ['', ''];
                            }).catch(() => $el.submit()).finally(() => $data.busy = false)
                        ">
                        @csrf
                        <div class="flex gap-3">
                            <x-ui.avatar :user="$me" size="size-11" />
                            <label for="body" class="sr-only">{{ __('Write a post') }}</label>
                            <textarea id="body" name="body" required minlength="2" maxlength="5000" x-on:focus="open = true" x-bind:rows="open ? 4 : 1" rows="1"
                                class="block min-h-11 w-full resize-none rounded-2xl border-0 bg-stone-100 px-4 py-2.5 text-base text-stone-900 placeholder:text-stone-500 focus:bg-white focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm"
                                placeholder="{{ __('Start a post, :name', ['name' => $firstName]) }}"
                                x-bind:placeholder="type === 'poll' ? @js(__('Ask a question…')) : @js(__('Start a post, :name', ['name' => $firstName]))">{{ old('body') }}</textarea>
                        </div>
                        @error('body')<p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                        @error('space_id')<p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror

                        {{-- Poll builder --}}
                        <div x-show="open && type === 'poll'" x-cloak class="mt-3 space-y-3 rounded-2xl bg-stone-50 p-3 ring-1 ring-stone-200 sm:p-4">
                            <p class="text-sm font-semibold text-stone-800">{{ __('Options') }}</p>
                            <template x-for="(option, i) in options" :key="i">
                                <div class="flex items-center gap-2">
                                    <label class="sr-only" x-bind:for="'poll-option-' + i" x-text="@js(__('Option')) + ' ' + (i + 1)"></label>
                                    <input type="text" name="options[]" x-bind:id="'poll-option-' + i" x-model="options[i]" maxlength="120" x-bind:placeholder="@js(__('Option')) + ' ' + (i + 1)"
                                        class="block min-h-11 w-full min-w-0 rounded-xl border-0 bg-white py-2.5 text-base text-stone-900 ring-1 ring-inset ring-stone-300 placeholder:text-stone-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">
                                    <button type="button" x-show="options.length > 2" x-on:click="options.splice(i, 1)" class="flex size-11 shrink-0 items-center justify-center rounded-xl text-stone-400 hover:bg-stone-200 hover:text-stone-700">
                                        <x-ui.icon name="x" size="size-4" /><span class="sr-only">{{ __('Remove option') }}</span>
                                    </button>
                                </div>
                            </template>
                            <button type="button" x-show="options.length < 6" x-on:click="options.push(''); $nextTick(() => document.getElementById('poll-option-' + (options.length - 1))?.focus())"
                                class="inline-flex min-h-11 items-center gap-1.5 rounded-xl px-2 text-sm font-semibold text-brand-700 hover:bg-white">
                                <x-ui.icon name="plus" size="size-4" /> {{ __('Add option') }}
                            </button>
                            @error('options')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                            @error('options.*')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-1">
                                    <label x-show="! formal" class="flex min-h-11 items-center gap-3 text-sm text-stone-700"><input type="checkbox" name="multiple" value="1" @checked(old('multiple')) class="size-5 rounded border-stone-300 text-brand-700 focus:ring-brand-600"> {{ __('Allow more than one choice') }}</label>
                                    <label class="flex min-h-11 items-center gap-3 text-sm text-stone-700"><input type="checkbox" name="anonymous" value="1" @checked(old('anonymous')) class="size-5 rounded border-stone-300 text-brand-700 focus:ring-brand-600"> {{ __('Anonymous voting') }}</label>
                                </div>
                                <x-ui.input name="closes_at" type="datetime-local" :label="__('Closes (optional for a quick poll)')" :value="old('closes_at')" />
                            </div>

                            @if ($formalSpaceIds)
                                <label x-show="formalSpaces.includes(space)" class="flex items-start gap-3 rounded-xl bg-white p-3 text-sm ring-1 ring-stone-200 has-[:checked]:ring-2 has-[:checked]:ring-brand-600">
                                    <input type="checkbox" name="formal" value="1" x-model="formal" class="mt-0.5 size-5 rounded border-stone-300 text-brand-700 focus:ring-brand-600">
                                    <span>
                                        <span class="block font-semibold text-stone-900">{{ __('Make this a formal vote') }}</span>
                                        <span class="block text-stone-500">{{ __('One final ballot per member school with dues paid. Results stay hidden until it closes, then lock and can be exported for the minutes. Needs a closing time.') }}</span>
                                    </span>
                                </label>
                                @error('formal')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                            @endif
                            @error('closes_at')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div x-show="open" x-cloak class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-[1fr_10rem_auto] sm:items-end">
                            <x-ui.select name="space_id" :label="__('Post to')" class="col-span-2 sm:col-span-1" :value="$defaultSpace"
                                :options="$postable->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()" />
                            <x-ui.select name="type" :label="__('Type')" :value="old('type', 'text')" :options="['text' => __('Update'), 'question' => __('Question'), 'showcase' => __('Showcase'), 'poll' => __('Poll')]" />
                            <x-ui.button type="submit" icon-right="send" class="self-end">{{ __('Post') }}</x-ui.button>
                        </div>

                        <div x-show="! open" class="mt-3 grid grid-cols-4 gap-1 border-t border-stone-100 pt-2">
                            @foreach ([['type' => 'question', 'icon' => 'circle-help', 'label' => __('Ask'), 'color' => 'text-sky-600'], ['type' => 'poll', 'icon' => 'chart-bar', 'label' => __('Poll'), 'color' => 'text-violet-600'], ['type' => 'showcase', 'icon' => 'trophy', 'label' => __('Showcase'), 'color' => 'text-amber-600'], ['type' => 'text', 'icon' => 'pen-line', 'label' => __('Update'), 'color' => 'text-brand-600']] as $quick)
                                <button type="button" class="flex min-h-11 min-w-0 flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-1 text-xs font-semibold text-stone-600 hover:bg-stone-100 sm:flex-row sm:gap-2 sm:text-sm"
                                    x-on:click="open = true; type = @js($quick['type']); $nextTick(() => { $root.querySelector('[name=type]').value = @js($quick['type']); $root.querySelector('#body').focus() })">
                                    <x-ui.icon :name="$quick['icon']" :class="$quick['color']" /> <span class="truncate">{{ $quick['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </form>
                </section>
            @endif

            <div class="flex items-center justify-between gap-2">
                <div class="-mx-1 flex min-w-0 gap-1.5 overflow-x-auto px-1 py-0.5">
                    @foreach (['all' => __('All'), 'chapter' => __('Chapter'), 'state' => __('State'), 'national' => __('National'), 'groups' => __('Groups')] as $value => $label)
                        <a href="{{ route('community.feed', array_filter(['level' => $value === 'all' ? null : $value, 'sort' => $sort === 'active' ? 'active' : null])) }}"
                            @class(['flex min-h-10 shrink-0 items-center rounded-full px-3.5 text-sm font-semibold ring-1 ring-inset',
                                'bg-brand-700 text-white ring-brand-700' => ! $spaceFilter && $level === $value,
                                'bg-white text-stone-700 ring-stone-200 hover:bg-stone-50' => $spaceFilter || $level !== $value])>{{ $label }}</a>
                    @endforeach
                </div>
                <a href="{{ request()->fullUrlWithQuery(['sort' => $sort === 'active' ? null : 'active', 'page' => null]) }}" class="flex min-h-11 shrink-0 items-center gap-1.5 rounded-xl px-2 text-sm font-semibold text-stone-600 hover:bg-stone-100" title="{{ __('Change order') }}">
                    <x-ui.icon name="arrow-down-up" size="size-4" /> <span class="hidden sm:inline">{{ $sort === 'active' ? __('Most active') : __('Latest') }}</span>
                </a>
            </div>

            <div data-feed-list class="space-y-4">
            @forelse ($posts as $post)
                @include('community::partials.post-card', ['liked' => in_array($post->id, $likedIds, true)])
            @empty
                <div class="rounded-2xl bg-white shadow-sm ring-1 ring-stone-200/80">
                    <x-ui.empty-state icon="newspaper" :title="__('Nothing here yet')" :description="__('Be the first to post. Your chapter sees it straight away.')" />
                </div>
            @endforelse
            </div>

            {{ $posts->links() }}
        </div>

        {{-- Right rail --}}
        <aside class="hidden space-y-4 lg:block">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200/80">
                <div class="h-14 bg-gradient-to-r from-brand-700 to-brand-500"></div>
                <div class="-mt-7 px-4 pb-4">
                    <x-ui.avatar :user="$me" size="size-14" class="ring-4" />
                    <p class="mt-2 font-semibold text-stone-900">{{ $me->name }}</p>
                    <p class="text-xs text-stone-500">{{ $directory->badgeFor($me) }}</p>
                </div>
                <nav class="border-t border-stone-100 py-1 text-sm" aria-label="{{ __('Shortcuts') }}">
                    <a href="{{ route('community.groups.index') }}" class="flex min-h-11 items-center justify-between px-4 font-medium text-stone-700 hover:bg-stone-50"><span class="flex items-center gap-2"><x-ui.icon name="users" size="size-4" class="text-stone-400" /> {{ __('My groups') }}</span><span class="text-stone-500 tabular">{{ $spaces->filter->isGroup()->count() }}</span></a>
                    <a href="{{ route('community.chat.index') }}" class="flex min-h-11 items-center gap-2 px-4 font-medium text-stone-700 hover:bg-stone-50"><x-ui.icon name="message-circle" size="size-4" class="text-stone-400" /> {{ __('Messages') }}</a>
                    <a href="{{ route('notifications.index') }}" class="flex min-h-11 items-center justify-between px-4 font-medium text-stone-700 hover:bg-stone-50"><span class="flex items-center gap-2"><x-ui.icon name="bell" size="size-4" class="text-stone-400" /> {{ __('Notifications') }}</span>@if ($unreadNotifications)<span class="rounded-full bg-red-600 px-1.5 text-xs font-bold leading-5 text-white tabular">{{ $unreadNotifications }}</span>@endif</a>
                </nav>
            </section>

            <x-ui.card :title="__('Your spaces')" :padding="false">
                <ul class="py-1">
                    @foreach ($spaces as $space)
                        <li><a href="{{ route('community.feed', ['space' => $space->id]) }}" @class(['flex min-h-11 items-center gap-3 px-5 text-sm font-medium hover:bg-stone-50', 'text-brand-700' => $spaceFilter?->is($space), 'text-stone-700' => ! $spaceFilter?->is($space)])>
                            <x-ui.icon :name="match ($space->type) { 'national' => 'flag', 'state' => 'map', 'chapter' => 'map-pin', 'committee' => 'lock', default => 'users' }" size="size-4" class="text-stone-400" />
                            <span class="truncate">{{ $space->name }}</span>
                        </a></li>
                    @endforeach
                </ul>
            </x-ui.card>

            @if ($openQuestions->isNotEmpty())
                <x-ui.card :title="__('Questions to answer')" :padding="false">
                    <ul class="divide-y divide-stone-100">
                        @foreach ($openQuestions as $question)
                            <li><a href="{{ route('community.forums.threads.show', [$question->forum_id, $question]) }}" class="block px-5 py-3 hover:bg-stone-50">
                                <span class="line-clamp-2 text-sm font-medium text-stone-900">{{ $question->title }}</span>
                                <span class="text-xs text-stone-500">{{ __($question->forum->name) }} · {{ trans_choice(':count answer|:count answers', $question->replies_count) }}</span>
                            </a></li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @if ($suggestedGroups->isNotEmpty())
                <x-ui.card :title="__('Groups to join')" :padding="false">
                    <ul class="divide-y divide-stone-100">
                        @foreach ($suggestedGroups as $group)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <a href="{{ route('community.groups.show', $group) }}" class="min-w-0"><span class="block truncate text-sm font-medium text-stone-900">{{ $group->name }}</span><span class="text-xs text-stone-500">{{ trans_choice(':count member|:count members', $group->active_members_count) }}</span></a>
                                <form method="POST" action="{{ route('community.groups.join', $group) }}">@csrf<x-ui.button type="submit" size="sm" variant="secondary">{{ $group->privacy === 'request' ? __('Ask') : __('Join') }}</x-ui.button></form>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <p class="px-2 text-xs text-stone-500">{{ __('Members only. Be respectful, keep school and parent data private, no adverts.') }}</p>
        </aside>
    </div>

    @include('community::partials.report-modal')
</x-layouts.app>
