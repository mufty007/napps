<x-layouts.app :title="$group->name">
    <a href="{{ route('community.groups.index') }}" class="mb-2 inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
        <x-ui.icon name="arrow-left" size="size-4" /> {{ __('Groups') }}
    </a>
    <x-ui.page-header :eyebrow="$group->typeLabel().' · '.$group->privacyLabel()" :title="$group->name" :description="$group->description">
        <x-slot:actions>
            @if ($status === 'active')
                @if ($group->type === 'committee')
                    <x-ui.button :href="route('community.chat.channel', $group)" variant="secondary" icon="message-circle">{{ __('Channel') }}</x-ui.button>
                @endif
                <form method="POST" action="{{ route('community.groups.leave', $group) }}">@csrf<x-ui.button type="submit" variant="ghost" icon="log-out">{{ __('Leave') }}</x-ui.button></form>
            @elseif ($status === 'invited')
                <form method="POST" action="{{ route('community.groups.accept', $group) }}">@csrf<x-ui.button type="submit">{{ __('Accept invitation') }}</x-ui.button></form>
            @elseif ($status === 'pending')
                <x-ui.badge tone="warning" class="text-sm">{{ __('Request sent') }}</x-ui.badge>
            @elseif ($group->type === 'interest' && in_array($group->privacy, ['open', 'request'], true))
                <form method="POST" action="{{ route('community.groups.join', $group) }}">@csrf<x-ui.button type="submit">{{ $group->privacy === 'request' ? __('Ask to join') : __('Join group') }}</x-ui.button></form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $canSee)
        <x-ui.card><x-ui.empty-state icon="lock" :title="__('Members only')" :description="__('Ask to join. A group admin will review your request.')" /></x-ui.card>
    @else
        <nav class="mb-6 flex gap-1 rounded-2xl bg-stone-100 p-1" aria-label="{{ __('Group') }}">
            @foreach (['posts' => __('Posts'), 'members' => __('Members :count', ['count' => $group->active_members_count])] as $value => $label)
                <a href="{{ route('community.groups.show', [$group, 'tab' => $value === 'posts' ? null : $value]) }}" @class(['flex min-h-11 flex-1 items-center justify-center rounded-xl px-3 text-sm font-semibold sm:flex-none', 'bg-white text-brand-800 shadow-sm' => $tab === $value, 'text-stone-600' => $tab !== $value]) @if ($tab === $value) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($tab === 'posts')
            <div class="mx-auto max-w-3xl space-y-4">
                @if ($canPost)
                    <x-ui.card>
                        <form method="POST" action="{{ route('community.feed.store') }}" class="flex items-end gap-2">@csrf
                            <input type="hidden" name="space_id" value="{{ $group->id }}">
                            <label for="group-post" class="sr-only">{{ __('Write a post') }}</label>
                            <textarea id="group-post" name="body" rows="2" required minlength="2" class="block min-w-0 flex-1 rounded-xl border-0 py-2.5 text-base shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm" placeholder="{{ __('Post to :group…', ['group' => $group->name]) }}"></textarea>
                            <x-ui.button type="submit" icon="send" class="shrink-0"><span class="sr-only sm:not-sr-only">{{ __('Post') }}</span></x-ui.button>
                        </form>
                    </x-ui.card>
                @endif
                @forelse ($posts as $post)
                    @include('community::partials.post-card', ['post' => $post->setRelation('space', $group), 'liked' => in_array($post->id, $likedIds, true), 'showSpace' => false])
                @empty
                    <x-ui.card><x-ui.empty-state icon="newspaper" :title="__('No posts yet')" /></x-ui.card>
                @endforelse
                {{ $posts?->links() }}
            </div>
        @else
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="space-y-6">
                    @if ($pending->isNotEmpty())
                        <x-ui.card :title="__('Asking to join')" :padding="false">
                            <ul class="divide-y divide-stone-100">
                                @foreach ($pending as $member)
                                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                        <span class="flex items-center gap-3"><x-ui.avatar :user="$member" /><span class="font-medium text-stone-900">{{ $member->name }}</span></span>
                                        <span class="flex gap-2">
                                            <form method="POST" action="{{ route('community.groups.respond', [$group, $member]) }}">@csrf<input type="hidden" name="decision" value="approve"><x-ui.button type="submit" size="sm">{{ __('Approve') }}</x-ui.button></form>
                                            <form method="POST" action="{{ route('community.groups.respond', [$group, $member]) }}">@csrf<input type="hidden" name="decision" value="decline"><x-ui.button type="submit" size="sm" variant="ghost">{{ __('Decline') }}</x-ui.button></form>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </x-ui.card>
                    @endif

                    <x-ui.card :title="__('Members')" :padding="false">
                        <ul class="divide-y divide-stone-100">
                            @foreach ($members as $member)
                                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                    <span class="flex min-w-0 items-center gap-3">
                                        <x-ui.avatar :user="$member" />
                                        <span class="min-w-0"><span class="block truncate font-medium text-stone-900">{{ $member->name }}</span><span class="block truncate text-xs text-stone-500">{{ $directory->badgeFor($member) }}</span></span>
                                        @if ($member->pivot->role === 'admin')<x-ui.badge tone="brand">{{ __('Admin') }}</x-ui.badge>@endif
                                    </span>
                                    @if ($isAdmin && ! $member->is(auth()->user()))
                                        <span class="flex gap-1">
                                            @if ($member->pivot->role !== 'admin')
                                                <form method="POST" action="{{ route('community.groups.admin', [$group, $member]) }}">@csrf<x-ui.button type="submit" size="sm" variant="ghost">{{ __('Make admin') }}</x-ui.button></form>
                                            @endif
                                            <form method="POST" action="{{ route('community.groups.remove', [$group, $member]) }}">@csrf @method('DELETE')<x-ui.button type="submit" size="sm" variant="ghost" class="text-red-700">{{ __('Remove') }}</x-ui.button></form>
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                </div>

                @if ($isAdmin)
                    <x-ui.card :title="__('Invite members')">
                        <form method="GET" action="{{ route('community.groups.show', $group) }}" class="flex gap-2">
                            <input type="hidden" name="tab" value="members">
                            <x-ui.input name="find" type="search" :label="__('Name or phone')" :value="request('find')" class="min-w-0 flex-1" />
                            <x-ui.button type="submit" variant="secondary" icon="search" class="self-end"><span class="sr-only">{{ __('Search') }}</span></x-ui.button>
                        </form>
                        <ul class="mt-3 space-y-1">
                            @foreach ($candidates as $person)
                                <li class="flex items-center justify-between gap-2">
                                    <span class="min-w-0 truncate text-sm font-medium text-stone-900">{{ $person->name }}</span>
                                    <form method="POST" action="{{ route('community.groups.invite', $group) }}">@csrf<input type="hidden" name="user_id" value="{{ $person->id }}"><x-ui.button type="submit" size="sm" variant="secondary">{{ __('Invite') }}</x-ui.button></form>
                                </li>
                            @endforeach
                        </ul>
                        @if ($invited->isNotEmpty())
                            <p class="mt-4 text-sm text-stone-500">{{ __('Invited: :names', ['names' => $invited->pluck('name')->join(', ')]) }}</p>
                        @endif
                    </x-ui.card>
                @endif
            </div>
        @endif
    @endif

    @include('community::partials.report-modal')
</x-layouts.app>
