{{-- Comments for a post: list + composer. Used inline in the feed (fetched on demand) and on the post page. Needs: $post, $comments, $canPost, $directory. --}}
<div class="space-y-4">
    <ul id="comments-{{ $post->id }}" class="space-y-4">
        @foreach ($comments as $comment)
            @include('community::partials.comment-item', ['isReply' => false])
        @endforeach
    </ul>
    @if ($canPost)
        <form method="POST" action="{{ route('community.posts.comments', $post) }}" data-target="comments-{{ $post->id }}" x-data="commentForm" x-on:submit.prevent="submit($el)" class="flex items-end gap-2">
            @csrf
            <x-ui.avatar :user="auth()->user()" size="size-8" class="mb-1.5" />
            <label for="comment-body-{{ $post->id }}" class="sr-only">{{ __('Write a comment') }}</label>
            <input id="comment-body-{{ $post->id }}" name="body" required maxlength="3000" autocomplete="off" class="block min-h-11 min-w-0 flex-1 rounded-full border-0 bg-stone-100 px-4 text-base text-stone-900 placeholder:text-stone-500 focus:bg-white focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm" placeholder="{{ __('Write a comment…') }}">
            <button type="submit" class="flex size-11 shrink-0 items-center justify-center rounded-full text-brand-700 hover:bg-brand-50 disabled:opacity-50" x-bind:disabled="busy"><x-ui.icon name="send" /><span class="sr-only">{{ __('Comment') }}</span></button>
        </form>
    @endif
</div>
