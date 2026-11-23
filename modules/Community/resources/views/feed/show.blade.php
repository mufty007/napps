<x-layouts.app :title="__('Post')">
    <a href="{{ route('community.feed', ['space' => $post->space_id]) }}" class="mb-4 inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
        <x-ui.icon name="arrow-left" size="size-4" /> {{ $post->space->name }}
    </a>

    <div class="mx-auto max-w-3xl space-y-4">
        @if ($post->hidden_at)
            <x-ui.alert tone="warning">{{ __('This post is hidden from members by a moderator.') }}</x-ui.alert>
        @endif

        @include('community::partials.post-card', ['full' => true, 'showSpace' => true, 'post' => $post->loadCount('comments')])

        <x-ui.card id="comments" :title="trans_choice(':count comment|:count comments', $comments->sum(fn ($c) => 1 + $c->replies->count()))">
            @include('community::partials.comment-thread', ['comments' => $comments, 'canPost' => $canPost])
        </x-ui.card>
    </div>

    @include('community::partials.report-modal')
</x-layouts.app>
