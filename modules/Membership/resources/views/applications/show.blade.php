<x-layouts.app :title="$application->school->name">
    <x-ui.page-header
        :eyebrow="__('Application :ref', ['ref' => $application->reference()])"
        :title="$application->school->name"
        :description="__('From :name (:phone), sent :date.', ['name' => $application->applicant->name, 'phone' => \App\Support\PhoneNumber::format($application->applicant->phone), 'date' => $application->submitted_at->format('j M Y')])"
    >
        <x-slot:actions>
            <x-ui.badge :tone="$application->status->tone()" dot class="text-sm">{{ $application->status->label() }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6 lg:order-2">
            @canany(['verify', 'approve'], $application)
                <x-ui.card :title="__('Your decision')">
                    @can('verify', $application)
                        <p class="text-sm text-stone-600">
                            {{ $stateApprovalRequired
                                ? __('Check the school exists at this address and the CAC certificate matches. Verifying sends it to :state State for approval.', ['state' => $application->school->state?->name])
                                : __('Check the school exists at this address and the CAC certificate matches. Approving issues the membership number and certificate.') }}
                        </p>
                        <form method="POST" action="{{ route('membership.applications.verify', $application) }}" class="mt-4 space-y-4">
                            @csrf
                            <div class="space-y-1.5">
                                <label for="note" class="block text-sm font-medium text-stone-800">{{ __('Note for the state (optional)') }}</label>
                                <textarea id="note" name="note" rows="3" class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm" placeholder="{{ __('e.g. Visited on 12 March; premises and documents in order.') }}">{{ old('note') }}</textarea>
                            </div>
                            <x-ui.button type="submit" class="w-full" icon="badge-check">{{ $stateApprovalRequired ? __('Verify and send to state') : __('Approve school') }}</x-ui.button>
                        </form>
                    @endcan

                    @can('approve', $application)
                        @if ($application->verification_note)
                            <div class="mb-4 rounded-xl bg-stone-50 p-4 text-sm ring-1 ring-stone-200">
                                <p class="font-semibold text-stone-900">{{ __('Chapter note') }}</p>
                                <p class="mt-1 text-stone-700">{{ $application->verification_note }}</p>
                            </div>
                        @endif
                        <p class="text-sm text-stone-600">{{ __('Approving issues the membership number, sends the certificate with its QR code, and bills this year\'s dues.') }}</p>
                        <form method="POST" action="{{ route('membership.applications.approve', $application) }}" class="mt-4">
                            @csrf
                            <x-ui.button type="submit" class="w-full" icon="badge-check">{{ __('Approve school') }}</x-ui.button>
                        </form>
                    @endcan

                    <x-ui.button variant="secondary" class="mt-3 w-full" icon="ban" x-on:click="$dispatch('open-modal', 'reject')">{{ __('Reject with a reason') }}</x-ui.button>
                </x-ui.card>

                <x-ui.modal name="reject" :title="__('Reject this application')">
                    <form method="POST" action="{{ route('membership.applications.reject', $application) }}" class="space-y-4">
                        @csrf
                        <div class="space-y-1.5">
                            <label for="reason" class="block text-sm font-medium text-stone-800">{{ __('Reason (sent to the applicant)') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                            <textarea id="reason" name="reason" rows="4" required minlength="5" class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm" placeholder="{{ __('e.g. The CAC certificate is for a different business name.') }}">{{ old('reason') }}</textarea>
                            @error('reason')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'reject')">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="submit" variant="danger">{{ __('Reject application') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>
                @error('reason')
                    <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'reject'))"></div>
                @enderror
            @endcanany

            @if ($application->status === \Modules\Membership\Enums\ApplicationStatus::Rejected)
                <x-ui.alert tone="danger" :title="__('Rejected by :name', ['name' => $application->rejecter?->name])">{{ $application->rejection_reason }}</x-ui.alert>
            @endif

            <x-ui.card :title="__('Progress')">
                @include('membership::partials.application-timeline')
                @if ($application->status === \Modules\Membership\Enums\ApplicationStatus::Approved)
                    <x-ui.button :href="route('membership.schools.show', $application->school_id)" variant="secondary" class="mt-6 w-full" icon-right="arrow-right">{{ __('Open school record') }}</x-ui.button>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6 lg:order-1">
            @include('membership::partials.application-details', ['documentRoute' => 'membership.applications.document'])
        </div>
    </div>
</x-layouts.app>
