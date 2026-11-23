{{-- Needs: $post (author, space, comments_count, reactions_count), $liked, $directory. Optional: $showSpace (true), $full (false). --}}
@php
    $showSpace = $showSpace ?? true;
    $full = $full ?? false;
    $viewer = auth()->user();
    $own = $post->author_id === $viewer->id;
    $canPin = $directory->canPin($viewer, $post->space);
    $canModerate = $directory->canModerate($viewer, $post->space);
    $long = mb_strlen($post->body) > 420;
@endphp
<article id="post-{{ $post->id }}" class="rounded-2xl bg-white ring-1 ring-stone-200" x-data="{ open: @js($full), loaded: @js($full), count: {{ (int) $post->comments_count }} }" x-on:comment-added="count++">
    <div class="px-5 pt-5">
        <header class="flex items-start gap-3">
            <x-ui.avatar :user="$post->author" size="size-10" />
            <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="font-semibold text-stone-900">{{ $post->author->name }}</span>
                    @if ($post->pinned)
                        <x-ui.badge tone="brand"><x-ui.icon name="pin" size="size-3" /> {{ __('Pinned') }}</x-ui.badge>
                    @endif
                    @if ($post->type === 'question')
                        <x-ui.badge tone="info">{{ __('Question') }}</x-ui.badge>
                    @elseif ($post->type === 'showcase')
                        <x-ui.badge tone="warning"><x-ui.icon name="trophy" size="size-3" /> {{ __('Showcase') }}</x-ui.badge>
                    @endif
                </p>
                <p class="mt-0.5 truncate text-xs text-stone-500">
                    @if ($badge = $directory->badgeFor($post->author)){{ $badge }} · @endif
                    @if ($showSpace)<a href="{{ route('community.feed', ['space' => $post->space_id]) }}" class="font-medium text-stone-600 hover:text-brand-700">{{ $post->space->name }}</a> · @endif
                    <a href="{{ route('community.posts.show', $post) }}" class="hover:text-brand-700"><time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->diffForHumans() }}</time></a>
                </p>
            </div>

            <x-ui.dropdown width="w-56">
                <x-slot:trigger>
                    <button type="button" class="-mr-2 -mt-1 flex size-11 items-center justify-center rounded-xl text-stone-400 hover:bg-stone-100 hover:text-stone-700">
                        <x-ui.icon name="ellipsis" />
                        <span class="sr-only">{{ __('Post options') }}</span>
                    </button>
                </x-slot:trigger>
                @if ($canPin)
                    <form method="POST" action="{{ route('community.posts.pin', $post) }}">
                        @csrf
                        <x-ui.dropdown-item :icon="$post->pinned ? 'pin-off' : 'pin'">{{ $post->pinned ? __('Unpin') : __('Pin to top') }}</x-ui.dropdown-item>
                    </form>
                @endif
                @unless ($own)
                    <x-ui.dropdown-item icon="flag" type="button" x-on:click="$dispatch('report', { type: 'post', id: {{ $post->id }} })">{{ __('Report') }}</x-ui.dropdown-item>
                @endunless
                @if ($own || $canModerate)
                    <form method="POST" action="{{ route('community.posts.destroy', $post) }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.dropdown-item icon="trash-2" class="text-red-700">{{ $own ? __('Delete post') : __('Hide post') }}</x-ui.dropdown-item>
                    </form>
                @endif
            </x-ui.dropdown>
        </header>

        <div @class(['mt-3 break-words text-[0.95rem] leading-relaxed text-stone-800', 'line-clamp-6' => $long && ! $full])>{{ \Modules\Community\Support\RichText::render($post->body) }}</div>
        @if ($long && ! $full)
            <a href="{{ route('community.posts.show', $post) }}" class="mt-1 inline-flex min-h-11 items-center text-sm font-semibold text-brand-700 hover:text-brand-800">{{ __('Read more') }}</a>
        @endif

        @if ($post->relationLoaded('poll') && $post->poll)
            @include('community::polls.card', ['poll' => $post->poll->setRelation('post', $post)])
        @endif
    </div>

    <footer class="mt-3 flex items-center gap-1 border-t border-stone-100 px-2 py-1">
        <form
            method="POST"
            action="{{ route('community.posts.react', $post) }}"
            x-data="likeButton(@js($liked), @js((int) $post->reactions_count))"
            x-on:submit.prevent="toggle($el)"
        >
            @csrf
            <button
                type="submit"
                class="flex min-h-11 items-center gap-2 rounded-xl px-3 text-sm font-semibold transition-colors hover:bg-stone-100"
                :class="liked ? 'text-red-600' : 'text-stone-600'"
                @class(['text-red-600' => $liked, 'text-stone-600' => ! $liked])
                aria-pressed="{{ $liked ? 'true' : 'false' }}"
                :aria-pressed="liked.toString()"
            >
                <x-ui.icon name="heart" x-bind:fill="liked ? 'currentColor' : 'none'" :fill="$liked ? 'currentColor' : 'none'" />
                <span class="tabular" x-text="count">{{ (int) $post->reactions_count }}</span>
                <span class="sr-only">{{ __('Like') }}</span>
            </button>
        </form>
        @unless ($full)
            <a href="{{ route('community.posts.show', $post) }}#comments"
                x-on:click.prevent="open = ! open; if (open && ! loaded) { loaded = true; fetch(@js(route('community.posts.comments.index', $post)), { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.text()).then(html => { $refs.comments.innerHTML = html }) }"
                x-bind:aria-expanded="open.toString()"
                class="flex min-h-11 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-stone-600 hover:bg-stone-100">
                <x-ui.icon name="message-circle" />
                <span class="tabular" x-text="count">{{ (int) $post->comments_count }}</span>
                <span class="hidden sm:inline">{{ __('Comment') }}</span>
            </a>
        @endunless
    </footer>

    @unless ($full)
        <div x-show="open" x-cloak class="border-t border-stone-100 px-5 py-4">
            <div x-ref="comments"><p class="text-sm text-stone-500">{{ __('Loading comments…') }}</p></div>
        </div>
    @endunless
</article>
