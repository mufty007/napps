<x-layouts.app :title="$post->exists ? __('Edit news') : __('New post')">
    <x-ui.page-header
        :title="$post->exists ? __('Edit news post') : __('New news post')"
        :description="__('Visible on the public News & updates page when published.')"
    />

    <form method="POST" action="{{ $post->exists ? route('news.update', $post) : route('news.store') }}" class="mx-auto max-w-2xl space-y-5">
        @csrf
        @if ($post->exists) @method('PUT') @endif

        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input name="title" :label="__('Title')" :value="old('title', $post->title)" required autofocus />
                <x-ui.input name="excerpt" :label="__('Short summary')" :value="old('excerpt', $post->excerpt)" :hint="__('Shown on listing cards.')" />
                <div>
                    <label for="body" class="block text-sm font-medium text-stone-800">{{ __('Body') }}</label>
                    <textarea id="body" name="body" rows="12" required class="mt-1.5 block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('body', $post->body) }}</textarea>
                    @error('body') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <input type="hidden" name="audience" value="public">
                <input type="hidden" name="level" value="national">
            </div>
        </x-ui.card>

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" name="publish" value="1" icon-right="send">{{ __('Publish') }}</x-ui.button>
            <x-ui.button type="submit" variant="secondary">{{ __('Save draft') }}</x-ui.button>
            @if ($post->exists && $post->isPublished())
                <x-ui.button type="submit" name="unpublish" value="1" variant="ghost">{{ __('Unpublish') }}</x-ui.button>
            @endif
            <x-ui.button :href="route('news.manage')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
