<x-layouts.public :title="__('News & updates')">
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
        <x-ui.page-header
            :eyebrow="__('From NAPPS')"
            :title="__('News & updates')"
            :description="__('Circulars, wins and association news for member schools and the public.')"
        />

        @if ($posts->isEmpty())
            <x-ui.empty-state icon="newspaper" :title="__('No posts yet')" :description="__('Check back soon for news from national, state and chapter leadership.')" />
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <a href="{{ route('news.show', $post) }}" class="group rounded-2xl bg-white p-6 ring-1 ring-stone-200 transition hover:ring-brand-300" x-reveal>
                        <p class="text-xs font-semibold uppercase tracking-wider text-brand-700">{{ $post->published_at?->format('j M Y') }}</p>
                        <h2 class="mt-2 text-lg font-semibold text-stone-900 group-hover:text-brand-800">{{ $post->title }}</h2>
                        <p class="mt-2 line-clamp-3 text-sm text-stone-600">{{ $post->excerpt ?: Str::limit(strip_tags($post->body), 140) }}</p>
                    </a>
                @endforeach
            </div>
            <div class="mt-10">{{ $posts->links() }}</div>
        @endif
    </section>
</x-layouts.public>
