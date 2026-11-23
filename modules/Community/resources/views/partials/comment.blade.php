@php $canRemove = $comment->author_id === auth()->id() || $directory->canModerate(auth()->user(), $post->space); @endphp
<div class="flex gap-3">
    <x-ui.avatar :user="$comment->author" size="size-8" />
    <div class="min-w-0 flex-1">
        <div class="rounded-2xl bg-stone-50 px-4 py-2.5">
            <p class="text-sm"><span class="font-semibold text-stone-900">{{ $comment->author->name }}</span> <span class="text-xs text-stone-500">· {{ $comment->created_at->diffForHumans() }}</span></p>
            @if ($comment->hidden_at)
                <p class="mt-0.5 text-sm italic text-stone-500">{{ __('Hidden by a moderator.') }}</p>
            @else
                <div class="mt-0.5 break-words text-sm text-stone-800">{{ \Modules\Community\Support\RichText::render($comment->body) }}</div>
            @endif
        </div>
        <div class="flex gap-1 pl-2 text-xs">
            @if ($comment->author_id !== auth()->id())
                <button type="button" class="min-h-9 px-1 font-semibold text-stone-400 hover:text-stone-700" x-on:click="$dispatch('report', { type: 'comment', id: {{ $comment->id }} })">{{ __('Report') }}</button>
            @endif
            @if ($canRemove && ! $comment->hidden_at)
                <form method="POST" action="{{ route('community.comments.destroy', $comment) }}">@csrf @method('DELETE')
                    <button type="submit" class="min-h-9 px-1 font-semibold text-stone-400 hover:text-red-700">{{ __('Remove') }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
