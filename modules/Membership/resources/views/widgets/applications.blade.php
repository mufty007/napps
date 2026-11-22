@if ($membershipApplications->isNotEmpty())
    <x-ui.card :title="__('Your membership applications')" :padding="false" class="mb-6" x-reveal>
        <ul class="divide-y divide-stone-100">
            @foreach ($membershipApplications as $application)
                <li>
                    <a href="{{ route('membership.apply.show', $application) }}" class="flex min-h-16 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 py-3.5 hover:bg-stone-50">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-stone-900">{{ $application->school->name }}</span>
                            <span class="block truncate text-sm text-stone-500">{{ $application->reference() }} · {{ $application->school->chapter?->name }}</span>
                        </span>
                        <x-ui.badge :tone="$application->status->tone()" dot>{{ $application->status->label() }}</x-ui.badge>
                    </a>
                </li>
            @endforeach
        </ul>
    </x-ui.card>
@endif
