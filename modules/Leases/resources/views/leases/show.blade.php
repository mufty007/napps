@php
    $dt = 'text-xs font-semibold uppercase tracking-wider text-stone-500';
    $dd = 'mt-0.5 text-sm font-medium text-stone-900';
    $days = $lease->daysToExpiry();
    $tone = $days < 0 ? 'neutral' : ($days <= 30 ? 'danger' : ($days <= 60 ? 'warning' : ($days <= 90 ? 'info' : 'neutral')));
    $canManage = Gate::allows('manage', $lease);
    $textarea = 'block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 placeholder:text-stone-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm';
@endphp

<x-layouts.app :title="\Illuminate\Support\Str::limit($lease->property_address, 40)">
    <x-ui.page-header
        :eyebrow="$lease->school?->name ?? __('Lease')"
        :title="\Illuminate\Support\Str::limit($lease->property_address, 60)"
        :description="__('Landlord: :name · Rent :rent :freq', ['name' => $lease->landlord_name, 'rent' => $lease->rent(), 'freq' => $lease->rent_frequency->perLabel()])"
    >
        <x-slot:actions>
            <x-ui.badge :tone="$lease->status->tone()" dot>{{ $lease->status->label() }}</x-ui.badge>
            @if ($canManage)
                <x-ui.button :href="route('leases.edit', $lease)" variant="secondary" icon="pencil">{{ __('Edit') }}</x-ui.button>
            @endif
            <x-ui.button :href="route('leases.index')" variant="secondary" icon="arrow-left">{{ __('All leases') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.stat :label="__('Rent')" :value="$lease->rent()" icon="banknote" />
        <x-ui.stat :label="__('Paid so far')" :value="\App\Support\Money::format($paidKobo)" icon="receipt" />
        <x-ui.stat :label="__('Ends')" :value="$lease->end_date->format('j M Y')" icon="calendar" />
        <x-ui.stat :label="__('Time left')" :value="$days < 0 ? __('Ended') : trans_choice(':count day|:count days', $days)" icon="calendar-clock" />
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('Rent log')" :description="__('Payments are added, never edited, so the trail stays honest.')" :padding="$payments->isEmpty()">
                @if ($canManage)
                    <x-slot:actions>
                        <x-ui.button variant="secondary" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'record-rent')">{{ __('Record rent') }}</x-ui.button>
                    </x-slot:actions>
                @endif

                @if ($payments->isEmpty())
                    <x-ui.empty-state icon="receipt" :title="__('No rent recorded yet')" class="py-4" />
                @else
                    <x-ui.table flush>
                        <x-slot:head>
                            <th scope="col">{{ __('Paid') }}</th>
                            <th scope="col" class="text-right">{{ __('Amount') }}</th>
                            <th scope="col" class="hidden sm:table-cell">{{ __('Method') }}</th>
                            <th scope="col" class="hidden md:table-cell">{{ __('By') }}</th>
                        </x-slot:head>
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="whitespace-nowrap tabular">{{ $payment->paid_at->format('j M Y') }}</td>
                                <td class="text-right font-semibold tabular text-stone-900">{{ $payment->amount() }}</td>
                                <td class="hidden sm:table-cell">{{ $payment->method ?: '—' }}</td>
                                <td class="hidden max-w-[10rem] truncate md:table-cell">{{ $payment->recorder?->name }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Document vault')" :description="__('Signed lease, receipts and correspondence.')" :padding="$documents->isEmpty()">
                @if ($canManage)
                    <x-slot:actions>
                        <div class="flex flex-wrap gap-2">
                            @if ($canGenerate)
                                <form method="POST" action="{{ route('leases.model-lease', $lease) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="accent" size="sm" icon="file-text">{{ __('Draft model lease') }}</x-ui.button>
                                </form>
                            @endif
                            <x-ui.button variant="secondary" size="sm" icon="upload" x-on:click="$dispatch('open-modal', 'upload-document')">{{ __('Upload') }}</x-ui.button>
                        </div>
                    </x-slot:actions>
                @endif

                @if ($documents->isEmpty())
                    <x-ui.empty-state icon="folder" :title="__('Vault is empty')" :description="__('Upload the signed lease, or draft one from the model template.')" class="py-4" />
                @else
                    <div class="divide-y divide-stone-100 px-5">
                        @foreach ($documents as $doc)
                            <x-ui.doc-row
                                :title="$doc->getCustomProperty('title') ?: $doc->file_name"
                                :meta="$doc->created_at->format('j M Y')"
                                status="ready"
                                :href="route('leases.documents.show', [$lease, $doc])"
                            />
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Disputes')" :description="__('Problems with the landlord. Chapter helps first, then the legal panel.')" :padding="$lease->disputes->isEmpty()">
                @if ($lease->disputes->isEmpty())
                    <x-ui.empty-state icon="scale" :title="__('No disputes')" class="py-4" />
                @else
                    <ul class="divide-y divide-stone-100">
                        @foreach ($lease->disputes as $dispute)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('leases.disputes.show', $dispute) }}" class="inline-flex min-h-11 items-center font-semibold text-brand-700 hover:text-brand-800">
                                        {{ \Illuminate\Support\Str::limit($dispute->summary, 80) }}
                                    </a>
                                    <p class="text-xs text-stone-500">{{ $dispute->created_at->format('j M Y') }}@if ($dispute->lawyer) · {{ $dispute->lawyer->name }}@endif</p>
                                </div>
                                <x-ui.badge :tone="$dispute->status->tone()" dot>{{ $dispute->status->label() }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($canManage && $canOpenDispute && ! $hasOpenDispute)
                    <div @class(['mt-4 border-t border-stone-100 pt-4', 'px-5 pb-5' => $lease->disputes->isNotEmpty()])>
                        <x-ui.button variant="secondary" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'open-dispute')">{{ __('Open a dispute') }}</x-ui.button>
                    </div>
                @elseif ($hasOpenDispute)
                    <div @class(['mt-4', 'px-5 pb-5' => $lease->disputes->isNotEmpty()])>
                        <x-ui.alert tone="warning">{{ __('There is already an open dispute on this lease.') }}</x-ui.alert>
                    </div>
                @endif
            </x-ui.card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-ui.side-panel :title="__('Lease details')">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="{{ $dt }}">{{ __('Status') }}</dt>
                        <dd class="mt-1"><x-ui.badge :tone="$lease->status->tone()">{{ $lease->status->label() }}</x-ui.badge></dd>
                    </div>
                    <div>
                        <dt class="{{ $dt }}">{{ __('Ends') }}</dt>
                        <dd class="{{ $dd }} tabular">
                            {{ $lease->end_date->format('j M Y') }}
                            <x-ui.badge :tone="$tone" class="ml-1">{{ $days < 0 ? __('Ended') : trans_choice(':count day|:count days', $days) }}</x-ui.badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="{{ $dt }}">{{ __('Term') }}</dt>
                        <dd class="{{ $dd }}">{{ $lease->start_date->format('j M Y') }} – {{ $lease->end_date->format('j M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $dt }}">{{ __('Rent') }}</dt>
                        <dd class="{{ $dd }} tabular">{{ $lease->rent() }} <span class="font-normal text-stone-500">{{ $lease->rent_frequency->perLabel() }}</span></dd>
                    </div>
                    <div>
                        <dt class="{{ $dt }}">{{ __('Landlord') }}</dt>
                        <dd class="{{ $dd }}">{{ $lease->landlord_name }}</dd>
                        @if ($lease->landlord_phone)
                            <dd class="text-xs text-stone-500">{{ \App\Support\PhoneNumber::format($lease->landlord_phone) }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="{{ $dt }}">{{ __('Address') }}</dt>
                        <dd class="{{ $dd }} whitespace-pre-line">{{ $lease->property_address }}</dd>
                    </div>
                    @if ($lease->notes)
                        <div>
                            <dt class="{{ $dt }}">{{ __('Notes') }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-sm text-stone-700">{{ $lease->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.side-panel>

            @if ($canManage)
                <x-ui.side-panel :title="__('Remove lease')" :description="__('Only if there is no dispute on record. Prefer marking as terminated.')">
                    <form method="POST" action="{{ route('leases.destroy', $lease) }}" onsubmit="return confirm('{{ __('Delete this lease from the register?') }}')">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="secondary" icon="trash-2">{{ __('Delete') }}</x-ui.button>
                    </form>
                </x-ui.side-panel>
            @endif
        </div>
    </div>

    @if ($canManage)
        <x-ui.modal name="record-rent" :title="__('Record rent payment')">
            <form method="POST" action="{{ route('leases.rent.store', $lease) }}" class="space-y-4">
                @csrf
                <x-ui.input name="amount" inputmode="decimal" :label="__('Amount')" prefix="₦" required />
                <x-ui.input name="paid_at" type="date" :label="__('Paid on')" :value="old('paid_at', today()->toDateString())" required />
                <x-ui.input name="method" :label="__('Method (optional)')" :placeholder="__('Transfer, cash, POS…')" />
                <div class="space-y-1.5">
                    <label for="rent_notes" class="block text-sm font-medium text-stone-800">{{ __('Notes (optional)') }}</label>
                    <textarea id="rent_notes" name="notes" rows="3" maxlength="2000" class="{{ $textarea }}">{{ old('notes') }}</textarea>
                </div>
                <x-ui.button type="submit" icon="check">{{ __('Save payment') }}</x-ui.button>
            </form>
        </x-ui.modal>

        <x-ui.modal name="upload-document" :title="__('Upload to vault')">
            <form method="POST" action="{{ route('leases.documents.store', $lease) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <x-ui.input name="title" :label="__('Title (optional)')" :placeholder="__('e.g. Signed lease 2026')" />
                <div class="space-y-1.5">
                    <label for="file" class="block text-sm font-medium text-stone-800">{{ __('File') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="file" type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                        class="block w-full min-h-11 text-sm text-stone-700 file:mr-3 file:min-h-11 file:rounded-xl file:border-0 file:bg-brand-50 file:px-4 file:font-semibold file:text-brand-800 hover:file:bg-brand-100">
                    <p class="text-sm text-stone-500">{{ __('PDF, photo or Word file, up to 10 MB.') }}</p>
                    @error('file')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>
                <x-ui.button type="submit" icon="upload">{{ __('Upload') }}</x-ui.button>
            </form>
        </x-ui.modal>

        @if ($canOpenDispute && ! $hasOpenDispute)
            <x-ui.modal name="open-dispute" :title="__('Open a dispute')">
                <form method="POST" action="{{ route('leases.disputes.store', $lease) }}" class="space-y-4">
                    @csrf
                    <div class="space-y-1.5">
                        <label for="summary" class="block text-sm font-medium text-stone-800">{{ __('What is the problem') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                        <textarea id="summary" name="summary" rows="5" maxlength="5000" required class="{{ $textarea }}"
                            placeholder="{{ __('e.g. landlord locked the gate, rent increase without notice, eviction threat…') }}">{{ old('summary') }}</textarea>
                        @error('summary')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                        <p class="text-sm text-stone-500">{{ __('Your chapter will be told so they can help.') }}</p>
                    </div>
                    <x-ui.button type="submit" icon="scale">{{ __('Open dispute') }}</x-ui.button>
                </form>
            </x-ui.modal>
        @endif
    @endif
</x-layouts.app>
