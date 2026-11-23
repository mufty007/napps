<x-layouts.app :title="__('Chat')">
    <x-ui.page-header :eyebrow="__('Community')" :title="__('Chat')" :description="__('Private messages and channels for your chapter and committees. Executives cannot read private chats.')" />


    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <x-ui.card :padding="$conversations->isEmpty()" :title="__('Conversations')">
            @if ($conversations->isEmpty())
                <x-ui.empty-state icon="message-circle" :title="__('No chats yet')" :description="__('Open your chapter channel, or find a member to message.')" />
            @else
                <ul class="divide-y divide-stone-100">
                    @foreach ($conversations as $conversation)
                        @php $last = $conversation->messages->first(); $count = $unread[$conversation->id] ?? 0; @endphp
                        <li>
                            <a href="{{ route('community.chat.show', $conversation) }}" class="flex min-h-16 items-center gap-3 px-5 py-3 hover:bg-stone-50">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                                    <x-ui.icon :name="$conversation->type === 'channel' ? 'hash' : ($conversation->type === 'group' ? 'users' : 'user')" size="size-[18px]" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center justify-between gap-2">
                                        <span @class(['truncate text-sm text-stone-900', 'font-semibold' => $count > 0, 'font-medium' => $count === 0])>{{ $conversation->displayTitle(auth()->user()) }}</span>
                                        @if ($last)<span class="shrink-0 text-xs text-stone-500">{{ $last->created_at->diffForHumans(short: true) }}</span>@endif
                                    </span>
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="truncate text-sm text-stone-500">{{ $last ? ($last->sender_id === auth()->id() ? __('You').': ' : '').$last->preview(60) : __('No messages yet') }}</span>
                                        @if ($count > 0)<span class="flex min-w-5 shrink-0 items-center justify-center rounded-full bg-brand-600 px-1.5 text-[0.7rem] font-bold leading-5 text-white tabular">{{ $count }}<span class="sr-only"> {{ __('unread') }}</span></span>@endif
                                    </span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <div class="space-y-6">
            @if ($channels->isNotEmpty())
                <x-ui.card :title="__('Channels')" :description="__('Official chat kept on record.')" :padding="false">
                    <ul class="divide-y divide-stone-100">
                        @foreach ($channels as $space)
                            <li><a href="{{ route('community.chat.channel', $space) }}" class="flex min-h-12 items-center gap-3 px-5 text-sm font-medium text-stone-800 hover:bg-stone-50"><x-ui.icon name="hash" size="size-4" class="text-stone-400" /> {{ $space->name }}</a></li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <x-ui.card :title="__('New message')">
                <form method="GET" action="{{ route('community.chat.index') }}" class="flex gap-2" role="search">
                    <x-ui.input name="find" type="search" :label="__('Find a member')" :value="$search" class="min-w-0 flex-1" :placeholder="__('Name or phone')" />
                    <x-ui.button type="submit" variant="secondary" icon="search" class="self-end"><span class="sr-only">{{ __('Search') }}</span></x-ui.button>
                </form>
                @if (mb_strlen($search) >= 2)
                    <ul class="mt-3 space-y-1">
                        @forelse ($people as $person)
                            <li>
                                <form method="POST" action="{{ route('community.chat.direct') }}">@csrf
                                    <input type="hidden" name="user_id" value="{{ $person->id }}">
                                    <button type="submit" class="flex min-h-11 w-full items-center gap-3 rounded-xl px-2 text-left text-sm hover:bg-stone-50">
                                        <x-ui.avatar :user="$person" size="size-8" />
                                        <span class="min-w-0 flex-1 truncate font-medium text-stone-900">{{ $person->name }}</span>
                                        <x-ui.icon name="send" size="size-4" class="text-stone-400" />
                                    </button>
                                </form>
                            </li>
                        @empty
                            <li class="text-sm text-stone-500">{{ __('No members match.') }}</li>
                        @endforelse
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
