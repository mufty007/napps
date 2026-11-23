@php
    $dt = 'text-xs font-semibold uppercase tracking-wider text-stone-500';
    $dd = 'mt-0.5 text-sm font-medium text-stone-900';
    $textarea = 'block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 placeholder:text-stone-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm';
@endphp

<x-layouts.app :title="__('Lease dispute')">
    <x-ui.page-header
        :eyebrow="$dispute->school?->name ?? __('Lease dispute')"
        :title="\Illuminate\Support\Str::limit($lease?->property_address ?? __('Dispute'), 60)"
        :description="__('Opened :date by :who', ['date' => $dispute->created_at->format('j M Y'), 'who' => $dispute->opener?->name ?? __('someone at the school')])"
    >
        <x-slot:actions>
            <x-ui.badge :tone="$dispute->status->tone()" dot>{{ $dispute->status->label() }}</x-ui.badge>
            <x-ui.button :href="route('leases.disputes.index')" variant="secondary" icon="arrow-left">{{ __('All disputes') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('What happened')">
                <p class="whitespace-pre-wrap text-sm leading-relaxed text-stone-700">{{ $dispute->summary }}</p>
            </x-ui.card>

            @if ($dispute->status === \Modules\Leases\Enums\DisputeStatus::Resolved)
                <x-ui.card :title="__('Resolution')">
                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-stone-700">{{ $dispute->resolution_notes ?: __('No notes recorded.') }}</p>
                    @if ($dispute->resolved_at)
                        <p class="mt-3 text-xs text-stone-500">{{ __('Resolved :date', ['date' => $dispute->resolved_at->format('j M Y, g:ia')]) }}</p>
                    @endif
                </x-ui.card>
            @endif

            @if ($canRequestHelp || $canEscalate || $canResolve)
                <x-ui.card :title="__('Next step')" :description="__('Chapter helps first. Escalate to the legal panel only when mediation is not enough.')">
                    <div class="space-y-5">
                        @if ($canRequestHelp)
                            <form method="POST" action="{{ route('leases.disputes.help', $dispute) }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" icon="users">{{ __('Mark as chapter helping') }}</x-ui.button>
                            </form>
                        @endif

                        @if ($canEscalate)
                            <form method="POST" action="{{ route('leases.disputes.escalate', $dispute) }}" class="space-y-4 border-t border-stone-100 pt-5">
                                @csrf
                                <x-ui.select
                                    name="lawyer_id"
                                    :label="__('Assign a panel lawyer (optional)')"
                                    :options="$lawyers"
                                    :placeholder="__('Assign later')"
                                    :value="old('lawyer_id', $dispute->assigned_lawyer_id)"
                                />
                                <x-ui.button type="submit" variant="accent" icon="scale">{{ __('Pass to legal panel') }}</x-ui.button>
                            </form>
                        @endif

                        @if ($canResolve)
                            <form method="POST" action="{{ route('leases.disputes.resolve', $dispute) }}" class="space-y-4 border-t border-stone-100 pt-5">
                                @csrf
                                <div class="space-y-1.5">
                                    <label for="resolution_notes" class="block text-sm font-medium text-stone-800">{{ __('How it was resolved') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                                    <textarea id="resolution_notes" name="resolution_notes" rows="4" maxlength="5000" required
                                        class="{{ $textarea }}"
                                        placeholder="{{ __('What was agreed with the landlord, and any follow-up.') }}">{{ old('resolution_notes') }}</textarea>
                                    @error('resolution_notes')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                                </div>
                                <x-ui.button type="submit" icon="check">{{ __('Mark resolved') }}</x-ui.button>
                            </form>
                        @endif
                    </div>
                </x-ui.card>
            @endif
        </div>

        <div class="min-w-0 space-y-6">
            <x-ui.side-panel :title="__('Dispute details')">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="{{ $dt }}">{{ __('Status') }}</dt>
                        <dd class="mt-1"><x-ui.badge :tone="$dispute->status->tone()">{{ $dispute->status->label() }}</x-ui.badge></dd>
                    </div>
                    <div>
                        <dt class="{{ $dt }}">{{ __('School') }}</dt>
                        <dd class="{{ $dd }}">{{ $dispute->school?->name }}</dd>
                        @if ($dispute->school?->chapter)
                            <dd class="text-xs text-stone-500">{{ $dispute->school->chapter->name }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="{{ $dt }}">{{ __('Landlord') }}</dt>
                        <dd class="{{ $dd }}">{{ $lease?->landlord_name ?? '—' }}</dd>
                        @if ($lease?->landlord_phone)
                            <dd class="text-xs text-stone-500">{{ \App\Support\PhoneNumber::format($lease->landlord_phone) }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="{{ $dt }}">{{ __('Lawyer') }}</dt>
                        <dd class="{{ $dd }}">{{ $dispute->lawyer?->name ?? __('Unassigned') }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $dt }}">{{ __('Opened by') }}</dt>
                        <dd class="{{ $dd }}">{{ $dispute->opener?->name ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($canViewLease && $lease)
                    <div class="mt-5 border-t border-stone-100 pt-5">
                        <x-ui.button :href="route('leases.show', $lease)" variant="secondary" icon="building-2">{{ __('Open lease') }}</x-ui.button>
                    </div>
                @elseif ($lease)
                    <p class="mt-5 border-t border-stone-100 pt-5 text-sm text-stone-600">{{ $lease->property_address }}</p>
                @endif
            </x-ui.side-panel>
        </div>
    </div>
</x-layouts.app>
