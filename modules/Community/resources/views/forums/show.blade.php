<x-layouts.app :title="__($forum->name)">
    <a href="{{ route('community.forums.index') }}" class="mb-2 inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
        <x-ui.icon name="arrow-left" size="size-4" /> {{ __('Forums') }}
    </a>
    <x-ui.page-header :eyebrow="$forum->space->name" :title="__($forum->name)" :description="$forum->description">
        @if ($canPost)
            <x-slot:actions>
                <x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'new-thread')">{{ __('New thread') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>


    <div class="-mx-1 mb-4 flex gap-1 overflow-x-auto px-1">
        @foreach (['all' => __('All'), 'questions' => __('Questions'), 'unanswered' => __('Unanswered'), 'answered' => __('Answered')] as $value => $label)
            <a href="{{ route('community.forums.show', [$forum, 'filter' => $value === 'all' ? null : $value]) }}"
                @class(['flex min-h-11 shrink-0 items-center rounded-full px-4 text-sm font-semibold ring-1 ring-inset',
                    'bg-brand-700 text-white ring-brand-700' => $filter === $value,
                    'bg-white text-stone-700 ring-stone-200 hover:bg-stone-50' => $filter !== $value])>{{ $label }}</a>
        @endforeach
    </div>

    <x-ui.card :padding="$threads->isEmpty()">
        @if ($threads->isEmpty())
            <x-ui.empty-state icon="messages-square" :title="__('No threads yet')" :description="__('Start one: ask a question or share what worked for your school.')" />
        @else
            <ul class="divide-y divide-stone-100">
                @foreach ($threads as $thread)
                    @include('community::forums.partials.thread-row')
                @endforeach
            </ul>
        @endif
    </x-ui.card>
    <div class="mt-6">{{ $threads->links() }}</div>

    @if ($canPost)
        <x-ui.modal name="new-thread" :title="__('New thread in :forum', ['forum' => __($forum->name)])" max-width="max-w-2xl">
            <form method="POST" action="{{ route('community.forums.threads.store', $forum) }}" class="space-y-4">
                @csrf
                <x-ui.input name="title" :label="__('Title')" required minlength="8" maxlength="180" :placeholder="__('e.g. How do you handle parents who pay only half?')" />
                <div class="space-y-1.5">
                    <label for="thread-body" class="block text-sm font-medium text-stone-800">{{ __('Details') }}</label>
                    <textarea id="thread-body" name="body" rows="6" required minlength="5" class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('body') }}</textarea>
                    @error('body')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>
                <x-ui.checkbox name="is_question" :label="__('This is a question (members can mark the best answer)')" :checked="true" />
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'new-thread')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" icon-right="send">{{ __('Post thread') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
        @if ($errors->hasAny(['title', 'body']))
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'new-thread'))"></div>
        @endif
    @endif
</x-layouts.app>
