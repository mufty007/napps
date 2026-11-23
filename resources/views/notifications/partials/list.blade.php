<div data-ajax-results>
    <div class="mb-4 flex gap-2">
        @foreach (['all' => __('All'), 'unread' => __('Unread :count', ['count' => $unreadCount ?: ''])] as $value => $label)
            <a href="{{ route('notifications.index', $value === 'all' ? [] : ['filter' => $value]) }}"
                data-ajax-tab="{{ $value }}"
                @class([
                    'flex min-h-11 items-center rounded-xl px-4 text-sm font-semibold ring-1 ring-inset',
                    'bg-brand-700 text-white ring-brand-700' => $filter === $value,
                    'bg-white text-stone-700 ring-stone-200 hover:bg-stone-50' => $filter !== $value,
                ])
                @if ($filter === $value) aria-current="page" @endif>{{ trim($label) }}</a>
        @endforeach
    </div>

    <x-ui.card :padding="$notifications->isEmpty()">
        @if ($notifications->isEmpty())
            <x-ui.empty-state icon="bell" :title="$filter === 'unread' ? __('Nothing unread') : __('No notifications yet')" :description="__('When someone replies to you, messages you or answers your question, it shows here.')" />
        @else
            <ul class="divide-y divide-stone-100">
                @foreach ($notifications as $notification)
                    @php $data = $notification->data; @endphp
                    <li>
                        <a href="{{ route('notifications.open', $notification->id) }}" @class(['flex min-h-16 gap-3 px-5 py-3.5 hover:bg-stone-50', 'bg-brand-50/50' => ! $notification->read_at])>
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white text-brand-700 ring-1 ring-stone-200">
                                <x-ui.icon :name="$data['icon'] ?? 'bell'" size="size-[18px]" />
                            </span>
                            <span class="min-w-0 flex-1 text-sm">
                                <span class="block font-semibold text-stone-900">{{ $data['title'] ?? __('Notification') }}</span>
                                @if (! empty($data['body']))
                                    <span class="line-clamp-2 block text-stone-600">{{ $data['body'] }}</span>
                                @endif
                                <span class="mt-0.5 block text-xs text-stone-500">{{ $notification->created_at->diffForHumans() }}</span>
                            </span>
                            @unless ($notification->read_at)
                                <span class="mt-2 size-2.5 shrink-0 rounded-full bg-brand-600"><span class="sr-only">{{ __('Unread') }}</span></span>
                            @endunless
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div class="mt-6">{{ $notifications->links() }}</div>
</div>
