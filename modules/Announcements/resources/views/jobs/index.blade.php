<x-layouts.public :title="__('Job board')">
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
        <x-ui.page-header
            :eyebrow="__('Careers')"
            :title="__('Job board')"
            :description="__('Roles at NAPPS member schools — teachers, bursars, administrators and more.')"
        />

        @if ($jobs->isEmpty())
            <x-ui.empty-state icon="briefcase" :title="__('No open roles')" :description="__('When schools and chapters post vacancies, they will appear here.')" />
        @else
            <div class="mt-10 space-y-4">
                @foreach ($jobs as $job)
                    <a href="{{ route('jobs.show', $job) }}" class="block rounded-2xl bg-white p-5 ring-1 ring-stone-200 transition hover:ring-brand-300" x-reveal>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-stone-900">{{ $job->title }}</h2>
                                <p class="mt-1 text-sm text-stone-600">{{ $job->employerName() }}@if ($job->location) · {{ $job->location }}@endif</p>
                            </div>
                            <x-ui.badge tone="brand">{{ $job->employmentTypeLabel() }}</x-ui.badge>
                        </div>
                        @if ($job->summary)
                            <p class="mt-3 line-clamp-2 text-sm text-stone-600">{{ $job->summary }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
            <div class="mt-10">{{ $jobs->links() }}</div>
        @endif
    </section>
</x-layouts.public>
