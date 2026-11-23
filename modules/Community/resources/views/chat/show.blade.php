<x-layouts.app :title="$conversation->displayTitle(auth()->user())">
    <div class="mx-auto flex max-w-3xl flex-col"
        x-data="chatRoom({
            id: {{ $conversation->id }},
            pollUrl: @js(route('community.chat.poll', $conversation)),
            sendUrl: @js(route('community.chat.messages', $conversation)),
            photoMaxKb: {{ \Modules\Community\Models\Message::PHOTO_MAX_KB }},
            messages: { tooBig: @js(__('That photo is over 5 MB. Choose a smaller one.')), notImage: @js(__('Choose a JPG, PNG or WebP photo.')), failed: @js(__('The photo did not send. Check your connection and try again.')) },
            lastId: {{ $lastId }},
            seenUpTo: @js($seenUpTo),
            realtime: @js($realtime),
            typing: @js($showTyping),
            me: {{ auth()->id() }},
        })">
        <div class="mb-3 flex items-center gap-2">
            <a href="{{ route('community.chat.index') }}" class="flex size-11 shrink-0 items-center justify-center rounded-xl text-stone-600 hover:bg-stone-100"><x-ui.icon name="arrow-left" /><span class="sr-only">{{ __('All chats') }}</span></a>
            <div class="min-w-0">
                <h1 class="truncate text-lg font-semibold text-stone-900">{{ $conversation->displayTitle(auth()->user()) }}</h1>
                <p class="truncate text-xs text-stone-500">
                    @if ($conversation->type === 'channel')
                        {{ __('Channel · kept on record') }}
                    @elseif ($conversation->type === 'direct')
                        {{ __('Private chat') }}
                    @else
                        {{ trans_choice(':count member|:count members', $conversation->members->count()) }}
                    @endif
                    <span x-show="whoIsTyping" x-cloak x-text="' · ' + whoIsTyping + ' ' + @js(__('is typing…'))"></span>
                </p>
            </div>
        </div>

        <div class="rounded-2xl bg-stone-100/70 ring-1 ring-stone-200">
            <div x-ref="scroller" class="h-[calc(100dvh-19rem)] min-h-72 overflow-y-auto px-3 py-4 sm:px-5 lg:h-[calc(100dvh-17rem)]">
                @if ($hasEarlier)
                    <div class="mb-3 text-center"><a href="{{ route('community.chat.show', [$conversation, 'before' => $messages->first()->id]) }}" class="inline-flex min-h-9 items-center rounded-full bg-white px-3 text-xs font-semibold text-stone-600 ring-1 ring-stone-200">{{ __('Earlier messages') }}</a></div>
                @endif
                <ul x-ref="list" class="space-y-3" aria-live="polite">
                    @forelse ($messages as $message)
                        @include('community::chat.partials.message')
                    @empty
                        <li class="py-10 text-center text-sm text-stone-500" data-empty>{{ __('Say hello. Messages appear here instantly.') }}</li>
                    @endforelse
                </ul>
                <p x-show="seenLabel" x-cloak x-text="seenLabel" class="mt-1 pr-1 text-right text-[0.7rem] font-medium text-stone-500"></p>
            </div>

            <form method="POST" action="{{ route('community.chat.messages', $conversation) }}" enctype="multipart/form-data" x-on:submit.prevent="send()" class="rounded-b-2xl border-t border-stone-200 bg-white p-2 sm:p-3">
                @csrf
                <div x-show="photoUrl" x-cloak class="mb-2 flex items-center gap-3 rounded-xl bg-stone-50 p-2 ring-1 ring-stone-200">
                    <img x-bind:src="photoUrl" alt="" class="size-14 rounded-lg object-cover">
                    <div class="min-w-0 flex-1 text-sm">
                        <p class="truncate font-semibold text-stone-800" x-text="photoName"></p>
                        <p class="text-xs text-stone-500">{{ __('Add a caption, or send the photo on its own.') }}</p>
                    </div>
                    <button type="button" x-on:click="clearPhoto()" class="flex size-11 shrink-0 items-center justify-center rounded-xl text-stone-500 hover:bg-stone-200 hover:text-stone-800">
                        <x-ui.icon name="x" size="size-4" /><span class="sr-only">{{ __('Remove photo') }}</span>
                    </button>
                </div>
                <p x-show="error" x-cloak x-text="error" class="mb-2 px-1 text-sm font-medium text-red-700" role="alert"></p>
                <div class="flex items-end gap-2">
                    <label class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-xl text-stone-500 hover:bg-stone-100 hover:text-brand-700 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand-600">
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" x-ref="photo" x-on:change="pickPhoto($event)">
                        <x-ui.icon name="image-plus" />
                        <span class="sr-only">{{ __('Attach a photo') }}</span>
                    </label>
                    <label for="chat-body" class="sr-only">{{ __('Message') }}</label>
                    <textarea id="chat-body" name="body" rows="1" maxlength="4000" x-ref="input" x-model="draft"
                        x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); send() }" x-on:input="typed()"
                        class="block max-h-40 min-h-11 min-w-0 flex-1 resize-none rounded-xl border-0 py-2.5 text-base text-stone-900 ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm"
                        placeholder="{{ __('Write a message…') }}"></textarea>
                    <x-ui.button type="submit" icon="send" class="shrink-0" x-bind:disabled="sending || (! draft.trim() && ! photo)"><span class="sr-only">{{ __('Send') }}</span></x-ui.button>
                </div>
            </form>
        </div>
        <p x-show="queued > 0" x-cloak class="mt-2 text-sm font-medium text-amber-700" role="status" x-text="queued + ' ' + @js(__('message(s) will send when you are back online.'))"></p>
    </div>

    @include('community::partials.report-modal')
</x-layouts.app>
