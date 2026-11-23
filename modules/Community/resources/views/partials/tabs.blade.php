{{-- Community sub-navigation (PRD: Feed, Forums, Groups, Chat, Notifications; moderators also get Reports). --}}
@php
    $tabs = [
        ['community.feed', __('Feed'), 'newspaper', ['community.feed', 'community.index', 'community.posts.*']],
        ['community.forums.index', __('Forums'), 'messages-square', ['community.forums.*']],
        ['community.groups.index', __('Groups'), 'users', ['community.groups.*']],
        ['community.chat.index', __('Chat'), 'message-circle', ['community.chat.*']],
        ['notifications.index', __('Notifications'), 'bell', ['notifications.*']],
    ];

    if (auth()->user()?->can('community.moderate')) {
        $tabs[] = ['community.moderation.index', __('Reports'), 'flag', ['community.moderation.*']];
    }
@endphp
<nav class="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="{{ __('Community') }}">
    <div class="flex w-max min-w-full gap-1 rounded-2xl bg-stone-100 p-1 sm:w-auto">
        @foreach ($tabs as [$route, $label, $icon, $patterns])
            @php $active = request()->routeIs(...$patterns); @endphp
            <a
                href="{{ route($route) }}"
                @class([
                    'inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl px-3.5 text-sm font-semibold transition-colors',
                    'bg-white text-brand-800 shadow-sm' => $active,
                    'text-stone-600 hover:text-stone-900' => ! $active,
                ])
                @if ($active) aria-current="page" @endif
            >
                <x-ui.icon :name="$icon" size="size-4" />
                {{ $label }}
            </a>
        @endforeach
    </div>
</nav>
