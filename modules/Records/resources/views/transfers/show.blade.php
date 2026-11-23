@php
    $dt = 'text-xs font-semibold uppercase tracking-wider text-stone-500';
    $dd = 'mt-0.5 text-sm font-medium text-stone-900';
    $overdue = $transfer->isOverdue();
@endphp
<x-layouts.app :title="__('Transfer clearance')">
    <x-ui.page-header :eyebrow="__('Transfer clearance')" :title="$transfer->student?->fullName() ?? __('Pupil')" :description="__('From :from to :to', ['from' => $transfer->fromSchool?->name, 'to' => $transfer->toSchool?->name ?? __('another school')])">
        <x-slot:actions>
            <x-ui.button :href="route('records.transfers.index')" variant="secondary" icon="arrow-left">{{ __('All transfers') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <x-ui.card :title="__('Request')">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="{{ $dt }}">{{ __('Status') }}</dt>
                        <dd class="mt-1"><x-ui.badge :tone="$overdue ? 'danger' : $transfer->status->tone()" dot>{{ $overdue ? __('Overdue') : $transfer->status->label() }}</x-ui.badge></dd>
                    </div>
                    <div><dt class="{{ $dt }}">{{ __('Reply due') }}</dt><dd class="{{ $dd }}">{{ $transfer->sla_due_at->format('l j M Y') }}</dd></div>
                    <div><dt class="{{ $dt }}">{{ __('Asked by') }}</dt><dd class="{{ $dd }}">{{ $transfer->requester?->name }} · {{ $transfer->toSchool?->name ?? __('Secretariat') }}</dd></div>
                    <div><dt class="{{ $dt }}">{{ __('Asked on') }}</dt><dd class="{{ $dd }}">{{ $transfer->created_at->format('j M Y, g:ia') }}</dd></div>
                    @if ($showPupilDetails && $transfer->student)
                        <div><dt class="{{ $dt }}">{{ __('Class') }}</dt><dd class="{{ $dd }}">{{ $transfer->student->class_name ?? '—' }}</dd></div>
                        <div><dt class="{{ $dt }}">{{ __('Guardian') }}</dt><dd class="{{ $dd }}">{{ $transfer->student->guardian_name }}</dd></div>
                    @endif
                </dl>
                @if ($overdue)
                    <x-ui.alert tone="warning" class="mt-4">{{ __('This reply is past the :days working day limit.', ['days' => \Modules\Records\Services\TransferService::SLA_WORKING_DAYS]) }}</x-ui.alert>
                @endif
            </x-ui.card>

            @if ($transfer->status->isFinal() && $transfer->status !== \Modules\Records\Enums\TransferStatus::Cancelled)
                <x-ui.card :title="__('Reply')">
                    <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div><dt class="{{ $dt }}">{{ __('Answered by') }}</dt><dd class="{{ $dd }}">{{ $transfer->responder?->name }} · {{ $transfer->responded_at?->format('j M Y, g:ia') }}</dd></div>
                        @if ($transfer->outstanding_kobo > 0)
                            <div><dt class="{{ $dt }}">{{ __('Outstanding') }}</dt><dd class="{{ $dd }} tabular">{{ $transfer->outstanding() }}</dd></div>
                        @endif
                    </dl>
                    @if ($transfer->response_notes)
                        <p class="mt-4 whitespace-pre-line rounded-xl bg-stone-50 p-4 text-sm text-stone-700 ring-1 ring-stone-200">{{ $transfer->response_notes }}</p>
                    @endif
                </x-ui.card>
            @endif

            @if ($canRespond)
                <x-ui.card :title="__('Answer this request')" :description="__('Clearing marks the pupil as transferred out of your school.')">
                    <form method="POST" action="{{ route('records.transfers.respond', $transfer) }}" class="space-y-5">
                        @csrf
                        <fieldset>
                            <legend class="mb-1.5 text-sm font-medium text-stone-800">{{ __('Your answer') }}</legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl p-3 ring-1 ring-inset ring-stone-300 has-[:checked]:bg-brand-50 has-[:checked]:ring-brand-600">
                                    <input type="radio" name="decision" value="cleared" class="size-5 text-brand-700 focus:ring-brand-600" @checked(old('decision', 'cleared') === 'cleared')>
                                    <span class="text-sm font-semibold text-stone-800">{{ __('Cleared: nothing owed') }}</span>
                                </label>
                                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl p-3 ring-1 ring-inset ring-stone-300 has-[:checked]:bg-red-50 has-[:checked]:ring-red-600">
                                    <input type="radio" name="decision" value="blocked" class="size-5 text-red-600 focus:ring-red-600" @checked(old('decision') === 'blocked')>
                                    <span class="text-sm font-semibold text-stone-800">{{ __('Blocked: fees outstanding') }}</span>
                                </label>
                            </div>
                            @error('decision')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                        </fieldset>

                        <x-ui.input name="outstanding" inputmode="decimal" prefix="₦" :label="__('Amount outstanding')" :value="\App\Support\Money::toNaira($suggested)" :hint="__('Only used when blocking. Pre-filled from the pupil\'s record.')" />

                        <div class="space-y-1.5">
                            <label for="response_notes" class="block text-sm font-medium text-stone-800">{{ __('Notes for the other school') }}</label>
                            <textarea id="response_notes" name="response_notes" rows="4" maxlength="2000"
                                class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm"
                                placeholder="{{ __('Required when blocking: what is owed and how to settle it.') }}">{{ old('response_notes') }}</textarea>
                            @error('response_notes')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <x-ui.button type="submit" icon-right="check">{{ __('Send reply') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($canCancel)
                <x-ui.side-panel :title="__('Withdraw request')" :description="__('Changed your mind or asked by mistake? Withdraw it before the school replies.')">
                    <form method="POST" action="{{ route('records.transfers.cancel', $transfer) }}" onsubmit="return confirm('{{ __('Withdraw this request?') }}')">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" icon="x">{{ __('Withdraw') }}</x-ui.button>
                    </form>
                </x-ui.side-panel>
            @endif

            @if ($showPupilDetails && $transfer->student && ! $transfer->student->trashed())
                <x-ui.side-panel :title="__('Pupil record')">
                    <x-ui.button :href="route('records.students.show', $transfer->student)" variant="secondary" icon="graduation-cap">{{ __('Open record') }}</x-ui.button>
                </x-ui.side-panel>
            @endif
        </div>
    </div>
</x-layouts.app>
