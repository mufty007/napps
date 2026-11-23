<x-layouts.public :title="$job->title">
    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
        <a href="{{ route('jobs.index') }}" class="inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
            <x-ui.icon name="arrow-left" size="size-4" /> {{ __('All jobs') }}
        </a>
        <div class="mt-6 flex flex-wrap gap-2">
            <x-ui.badge tone="brand">{{ $job->employmentTypeLabel() }}</x-ui.badge>
            @if ($job->closes_at)
                <x-ui.badge tone="warning">{{ __('Closes :date', ['date' => $job->closes_at->format('j M Y')]) }}</x-ui.badge>
            @endif
        </div>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">{{ $job->title }}</h1>
        <p class="mt-2 text-lg text-stone-600">{{ $job->employerName() }}@if ($job->location) · {{ $job->location }}@endif</p>
        @if ($job->summary)
            <p class="mt-6 text-base text-stone-700">{{ $job->summary }}</p>
        @endif
        <div class="mt-6 whitespace-pre-wrap text-base leading-relaxed text-stone-800">{{ $job->body }}</div>
        <div class="mt-8 flex flex-wrap gap-3">
            @if ($job->contact_email)
                <x-ui.button :href="'mailto:'.$job->contact_email" icon="mail">{{ __('Email to apply') }}</x-ui.button>
            @endif
            @if ($job->contact_phone)
                <x-ui.button :href="'tel:'.$job->contact_phone" variant="secondary" icon="phone">{{ \App\Support\PhoneNumber::format($job->contact_phone) }}</x-ui.button>
            @endif
        </div>
    </article>
</x-layouts.public>
