<li>
    <a href="{{ route('community.forums.threads.show', [$forum, $thread]) }}" class="flex gap-4 px-5 py-4 hover:bg-stone-50">
        <span @class([
            'flex size-11 shrink-0 flex-col items-center justify-center rounded-xl text-xs font-semibold tabular',
            'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' => $thread->isAnswered(),
            'bg-stone-100 text-stone-600' => ! $thread->isAnswered(),
        ])>
            <span class="text-sm">{{ $thread->replies_count }}</span>
            <span class="text-[0.6rem] font-medium">{{ trans_choice('reply|replies', $thread->replies_count) }}</span>
        </span>
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2">
                @if ($thread->isAnswered())
                    <x-ui.badge tone="success"><x-ui.icon name="check" size="size-3" /> {{ __('Answered') }}</x-ui.badge>
                @elseif ($thread->is_question)
                    <x-ui.badge tone="info">{{ __('Question') }}</x-ui.badge>
                @endif
                @if ($thread->isLocked())
                    <x-ui.badge><x-ui.icon name="lock" size="size-3" /> {{ __('Locked') }}</x-ui.badge>
                @endif
            </span>
            <span class="mt-1 block font-semibold text-stone-900">{{ $thread->title }}</span>
            <span class="mt-0.5 block truncate text-xs text-stone-500">
                {{ $thread->author?->name }} · {{ ($thread->last_activity_at ?? $thread->created_at)->diffForHumans() }}
                @if ($showForum ?? false) · {{ __($forum->name) }}, {{ $forum->space?->name }} @endif
            </span>
        </span>
    </a>
</li>
