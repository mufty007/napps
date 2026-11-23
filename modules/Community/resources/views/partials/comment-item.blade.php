{{-- One comment (and, for top-level comments, its replies and reply box). Needs: $comment, $post, $directory, $isReply, $canPost. --}}
<li id="comment-{{ $comment->id }}" x-data="{ replying: false }">
    @include('community::partials.comment', ['comment' => $comment])
    @unless ($isReply)
        <div class="ml-11">
            @if ($canPost ?? true)
                <button type="button" x-on:click="replying = ! replying" class="min-h-9 text-xs font-semibold text-stone-500 hover:text-brand-700">{{ __('Reply') }}</button>
            @endif
            <ul id="replies-{{ $comment->id }}" class="mt-1 space-y-3 border-l-2 border-stone-100 pl-4 empty:hidden">
                @foreach ($comment->replies as $reply)
                    @include('community::partials.comment-item', ['comment' => $reply, 'isReply' => true])
                @endforeach
            </ul>
            <form x-show="replying" x-cloak method="POST" action="{{ route('community.posts.comments', $post) }}" data-target="replies-{{ $comment->id }}" x-data="commentForm" x-on:submit.prevent="submit($el); replying = false" class="mt-2 flex items-end gap-2">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                <input name="body" required maxlength="3000" autocomplete="off" aria-label="{{ __('Reply to :name', ['name' => $comment->author->name]) }}" class="block min-h-11 min-w-0 flex-1 rounded-full border-0 bg-stone-100 px-4 text-base focus:bg-white focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm" placeholder="{{ __('Reply to :name…', ['name' => Str::before($comment->author->name, ' ')]) }}">
                <button type="submit" class="flex size-11 shrink-0 items-center justify-center rounded-full text-brand-700 hover:bg-brand-50"><x-ui.icon name="send" /><span class="sr-only">{{ __('Reply') }}</span></button>
            </form>
        </div>
    @endunless
</li>
