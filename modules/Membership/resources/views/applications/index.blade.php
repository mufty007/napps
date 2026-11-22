<x-layouts.app :title="__('Applications')">
    <x-ui.page-header
        :eyebrow="__('Membership')"
        :title="__('Membership applications')"
        :description="__('Chapters verify new schools; states approve them. Oldest waiting first.')"
    />

    <nav class="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="{{ __('Application status') }}">
        <div class="flex gap-2">
            @foreach (\Modules\Membership\Enums\ApplicationStatus::cases() as $tab)
                @php $active = $tab === $status; @endphp
                <a
                    href="{{ route('membership.applications.index', ['status' => $tab->value]) }}"
                    @class([
                        'flex min-h-11 shrink-0 items-center gap-2 rounded-xl px-4 text-sm font-semibold ring-1 ring-inset',
                        'bg-brand-700 text-white ring-brand-700' => $active,
                        'bg-white text-stone-700 ring-stone-200 hover:bg-stone-50' => ! $active,
                    ])
                    @if ($active) aria-current="page" @endif
                >
                    {{ $tab->shortLabel() }}
                    <span @class(['rounded-full px-2 py-0.5 text-xs tabular', 'bg-white/20' => $active, 'bg-stone-100 text-stone-600' => ! $active])>{{ number_format((int) ($counts[$tab->value] ?? 0)) }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    <x-ui.card :padding="$applications->isEmpty()">
        @if ($applications->isEmpty())
            <x-ui.empty-state
                icon="clipboard-check"
                :title="$status->isOpen() ? __('Nothing waiting for you') : __('No applications here yet')"
                :description="$status->isOpen() ? __('New applications from schools in your area appear here, and you get an SMS when one arrives.') : null"
            />
        @else
            <ul class="divide-y divide-stone-100">
                @foreach ($applications as $application)
                    <li>
                        <a href="{{ route('membership.applications.show', $application) }}" class="flex min-h-16 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 py-4 hover:bg-stone-50">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-stone-900">{{ $application->school->name }}</span>
                                <span class="block truncate text-sm text-stone-500">
                                    {{ $application->school->chapter?->name }}, {{ $application->school->state?->name }} · {{ $application->applicant->name }}
                                </span>
                            </span>
                            <span class="flex shrink-0 items-center gap-3 text-sm text-stone-500">
                                <span>{{ $application->reference() }}</span>
                                <span>{{ ($status->isOpen() ? $application->submitted_at : $application->updated_at)->diffForHumans() }}</span>
                                <x-ui.icon name="chevron-right" size="size-4" class="text-stone-400" />
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div class="mt-6">{{ $applications->links() }}</div>
</x-layouts.app>
