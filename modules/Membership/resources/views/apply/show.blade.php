<x-layouts.app :title="$application->reference()">
    <x-ui.page-header
        :eyebrow="__('Application :ref', ['ref' => $application->reference()])"
        :title="$application->school->name"
        :description="__('Sent :date. We text you when anything changes.', ['date' => $application->submitted_at->format('j M Y')])"
    >
        <x-slot:actions>
            <x-ui.badge :tone="$application->status->tone()" dot class="text-sm">{{ $application->status->label() }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6 lg:order-2">
            @if ($application->status === \Modules\Membership\Enums\ApplicationStatus::Approved)
                <x-ui.alert tone="success" :title="__('Welcome to NAPPS')">
                    {{ __(':school is a member school. Your proprietor screens are ready, and this year\'s dues are on your home page.', ['school' => $application->school->name]) }}
                </x-ui.alert>
            @elseif ($application->status === \Modules\Membership\Enums\ApplicationStatus::Rejected)
                <x-ui.alert tone="danger" :title="__('Not approved')">
                    <p>{{ $application->rejection_reason }}</p>
                    <p class="mt-2">{{ __('You can fix this and apply again.') }}</p>
                </x-ui.alert>
            @endif

            <x-ui.card :title="__('Progress')">
                @include('membership::partials.application-timeline')

                @if ($certificateUrl)
                    <div class="mt-6 flex flex-col gap-2">
                        <x-ui.button :href="$certificateUrl" icon="award" target="_blank" rel="noopener">{{ __('Download certificate') }}</x-ui.button>
                        <x-ui.button :href="route('dashboard')" variant="secondary" icon-right="arrow-right">{{ __('Go to your school') }}</x-ui.button>
                    </div>
                @elseif ($application->status === \Modules\Membership\Enums\ApplicationStatus::Rejected)
                    <x-ui.button :href="route('membership.apply')" class="mt-6 w-full" icon="rotate-ccw">{{ __('Apply again') }}</x-ui.button>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6 lg:order-1">
            @include('membership::partials.application-details', ['documentRoute' => 'membership.apply.document'])
        </div>
    </div>
</x-layouts.app>
