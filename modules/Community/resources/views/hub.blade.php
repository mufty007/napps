<x-layouts.app :title="__('Community')">
    <x-ui.page-header
        :eyebrow="__('Members only')"
        :title="__('Community')"
        :description="__('Talk, share and decide things together with proprietors in your chapter, your state and across NAPPS.')"
    />

    @php
        $spaces = [
            ['icon' => 'newspaper', 'title' => __('Feed'), 'text' => __('Posts from your chapter, state, national and the groups you follow.')],
            ['icon' => 'messages-square', 'title' => __('Forums'), 'text' => __('Ask questions on fees, legal, landlords, staffing and more.')],
            ['icon' => 'users', 'title' => __('Groups'), 'text' => __('Chapter, state, interest and committee groups.')],
            ['icon' => 'message-circle', 'title' => __('Chat'), 'text' => __('Direct messages and channels that replace scattered WhatsApp groups.')],
            ['icon' => 'bell', 'title' => __('Notifications'), 'text' => __('Replies, mentions, votes and announcements in one place.')],
        ];
    @endphp

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($spaces as $i => $space)
            <x-ui.card x-reveal="{{ $i * 40 }}">
                <div class="flex items-start gap-4">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700"><x-ui.icon :name="$space['icon']" /></span>
                    <div>
                        <h2 class="font-semibold text-stone-900">{{ $space['title'] }}</h2>
                        <p class="mt-1 text-sm text-stone-600">{{ $space['text'] }}</p>
                        <x-ui.badge class="mt-3">{{ __('Opening soon') }}</x-ui.badge>
                    </div>
                </div>
            </x-ui.card>
        @endforeach
    </div>
</x-layouts.app>
