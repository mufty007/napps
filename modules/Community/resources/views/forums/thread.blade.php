<x-layouts.app :title="$thread->title">
    <a href="{{ route('community.forums.show', $forum) }}" class="mb-4 inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
        <x-ui.icon name="arrow-left" size="size-4" /> {{ __($forum->name) }} · {{ $forum->space->name }}
    </a>

    <div class="mx-auto max-w-3xl space-y-4">
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200/80 sm:p-6">
            <div class="flex flex-wrap items-center gap-2">
                @if ($thread->isAnswered())
                    <x-ui.badge tone="success"><x-ui.icon name="check" size="size-3" /> {{ __('Answered') }}</x-ui.badge>
                @elseif ($thread->is_question)
                    <x-ui.badge tone="info">{{ __('Question') }}</x-ui.badge>
                @endif
                @if ($thread->isLocked())<x-ui.badge><x-ui.icon name="lock" size="size-3" /> {{ __('Locked') }}</x-ui.badge>@endif
            </div>
            <h1 class="mt-2 text-xl font-semibold tracking-tight text-stone-900 sm:text-2xl">{{ $thread->title }}</h1>
            <p class="mt-1 text-sm text-stone-500">{{ $thread->author?->name }} · {{ $thread->created_at->format('j M Y, g:ia') }}</p>
            <div class="mt-4 break-words leading-relaxed text-stone-800">{{ \Modules\Community\Support\RichText::render($thread->body) }}</div>

            <div class="mt-5 flex flex-wrap gap-2 border-t border-stone-100 pt-4">
                <form method="POST" action="{{ route('community.forums.threads.follow', [$forum, $thread]) }}">@csrf
                    <x-ui.button type="submit" variant="secondary" size="sm" :icon="$following ? 'bell-off' : 'bell'">{{ $following ? __('Following') : __('Follow') }}</x-ui.button>
                </form>
                @if ($canModerate)
                    <form method="POST" action="{{ route('community.forums.threads.lock', [$forum, $thread]) }}">@csrf
                        <x-ui.button type="submit" variant="ghost" size="sm" :icon="$thread->isLocked() ? 'lock-open' : 'lock'">{{ $thread->isLocked() ? __('Reopen') : __('Lock') }}</x-ui.button>
                    </form>
                @endif
                @if ($thread->author_id !== auth()->id())
                    <x-ui.button variant="ghost" size="sm" icon="flag" x-data x-on:click="$dispatch('report', { type: 'thread', id: {{ $thread->id }} })">{{ __('Report') }}</x-ui.button>
                @endif
            </div>
        </article>

        <h2 class="px-1 pt-2 text-base font-semibold text-stone-900">{{ trans_choice(':count answer|:count answers', $replies->count()) }}</h2>

        @foreach ($replies as $reply)
            @php $best = (int) $thread->best_comment_id === $reply->id; @endphp
            <article id="reply-{{ $reply->id }}" @class(['flex gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 sm:gap-4 sm:p-5', 'ring-2 ring-emerald-400' => $best, 'ring-stone-200/80' => ! $best, 'opacity-60' => $reply->hidden_at])>
                <form method="POST" action="{{ route('community.forums.replies.vote', [$forum, $thread, $reply]) }}" class="shrink-0">@csrf
                    @php $voted = in_array($reply->id, $votedIds, true); @endphp
                    <button type="submit" @disabled($reply->author_id === auth()->id()) class="flex min-h-14 w-11 flex-col items-center justify-center rounded-xl text-sm font-semibold ring-1 ring-inset disabled:opacity-50 {{ $voted ? 'bg-brand-50 text-brand-800 ring-brand-200' : 'text-stone-600 ring-stone-200 hover:bg-stone-50' }}" aria-pressed="{{ $voted ? 'true' : 'false' }}">
                        <x-ui.icon name="arrow-big-up" size="size-5" />
                        <span class="tabular">{{ $reply->votes_count }}</span>
                        <span class="sr-only">{{ __('Upvote') }}</span>
                    </button>
                </form>
                <div class="min-w-0 flex-1">
                    @if ($best)
                        <p class="mb-1 flex items-center gap-1 text-xs font-semibold text-emerald-700"><x-ui.icon name="award" size="size-4" /> {{ __('Best answer') }}</p>
                    @endif
                    <p class="text-sm"><span class="font-semibold text-stone-900">{{ $reply->author?->name }}</span> <span class="text-xs text-stone-500">· {{ $reply->created_at->diffForHumans() }}</span></p>
                    <div class="mt-1 break-words text-stone-800">{{ \Modules\Community\Support\RichText::render($reply->body) }}</div>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @if ($canMarkBest)
                            <form method="POST" action="{{ route('community.forums.replies.best', [$forum, $thread, $reply]) }}">@csrf
                                <button type="submit" class="min-h-9 rounded-lg px-2 text-xs font-semibold {{ $best ? 'text-stone-500 hover:text-stone-800' : 'text-emerald-700 hover:bg-emerald-50' }}">{{ $best ? __('Clear best answer') : __('Mark as best answer') }}</button>
                            </form>
                        @endif
                        @if ($reply->author_id !== auth()->id())
                            <button type="button" class="min-h-9 rounded-lg px-2 text-xs font-semibold text-stone-400 hover:text-stone-700" x-data x-on:click="$dispatch('report', { type: 'reply', id: {{ $reply->id }} })">{{ __('Report') }}</button>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach

        @if ($canPost)
            <x-ui.card :title="$thread->is_question ? __('Your answer') : __('Your reply')">
                <form method="POST" action="{{ route('community.forums.threads.reply', [$forum, $thread]) }}" class="space-y-3">@csrf
                    <label for="reply-body" class="sr-only">{{ __('Your answer') }}</label>
                    <textarea id="reply-body" name="body" rows="4" required minlength="2" class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm" placeholder="{{ __('Share what worked for your school…') }}"></textarea>
                    @error('body')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    <div class="flex justify-end"><x-ui.button type="submit" icon-right="send">{{ __('Post answer') }}</x-ui.button></div>
                </form>
            </x-ui.card>
        @elseif ($thread->isLocked())
            <x-ui.alert tone="info">{{ __('This thread is locked. New answers are closed.') }}</x-ui.alert>
        @endif
    </div>

    @include('community::partials.report-modal')
</x-layouts.app>
