<x-layouts.public :title="$post->title">
    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
        <a href="{{ route('news.index') }}" class="inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
            <x-ui.icon name="arrow-left" size="size-4" /> {{ __('All news') }}
        </a>
        <p class="mt-6 text-xs font-semibold uppercase tracking-wider text-brand-700">{{ $post->published_at?->format('j F Y') }}</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">{{ $post->title }}</h1>
        @if ($post->excerpt)
            <p class="mt-4 text-lg text-stone-600">{{ $post->excerpt }}</p>
        @endif
        <div class="prose prose-stone mt-8 max-w-none whitespace-pre-wrap text-base leading-relaxed text-stone-800">{{ $post->body }}</div>
    </article>
</x-layouts.public>
