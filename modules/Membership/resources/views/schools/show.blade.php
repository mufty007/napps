<x-layouts.app :title="$school->name">
    @php $unpaid = $dues->where('status', \Modules\Membership\Enums\DueStatus::Unpaid); @endphp

    <x-ui.page-header
        :eyebrow="$school->membership_number ?? __('Member school')"
        :title="$school->name"
        :description="$school->chapter?->name.', '.$school->state?->name"
    >
        <x-slot:actions>
            <x-ui.badge :tone="$school->membership_status->tone()" dot class="text-sm">{{ $school->membership_status->label() }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <x-ui.card :title="__('Dues')" :padding="$dues->isEmpty()">
                @can('membership.dues.record')
                    @if ($unpaid->isNotEmpty())
                        <x-slot:actions>
                            <x-ui.button variant="secondary" size="sm" icon="banknote" x-on:click="$dispatch('open-modal', 'record-payment')">{{ __('Record payment') }}</x-ui.button>
                        </x-slot:actions>
                    @endif
                @endcan

                @if ($dues->isEmpty())
                    <x-ui.empty-state icon="hand-coins" :title="__('No dues billed yet')" class="py-4" />
                @else
                    <x-ui.table flush>
                        <x-slot:head>
                            <th scope="col">{{ __('Charge') }}</th>
                            <th scope="col" class="text-right">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                        </x-slot:head>
                        @foreach ($dues as $due)
                            <tr>
                                <td>
                                    <span class="block font-medium text-stone-900">{{ $due->charge->title }}</span>
                                    <span class="block text-xs text-stone-500">{{ $due->charge->placeName() }} · {{ __('due :date', ['date' => $due->due_date->format('j M Y')]) }}</span>
                                </td>
                                <td class="text-right tabular">{{ \App\Support\Money::format($due->amount_kobo) }}</td>
                                <td>
                                    <x-ui.badge :tone="$due->isOverdue() ? 'danger' : $due->status->tone()">{{ $due->isOverdue() ? __('Overdue') : $due->status->label() }}</x-ui.badge>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Payments')" :padding="$payments->isEmpty()">
                @if ($payments->isEmpty())
                    <x-ui.empty-state icon="receipt" :title="__('No payments yet')" class="py-4" />
                @else
                    <ul class="divide-y divide-stone-100">
                        @foreach ($payments as $payment)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <span class="min-w-0 text-sm">
                                    <span class="block font-semibold text-stone-900 tabular">{{ \App\Support\Money::format($payment->amount_kobo) }} · {{ $payment->method->label() }}</span>
                                    <span class="block text-stone-500">
                                        {{ $payment->paid_at->format('j M Y') }} · {{ $payment->receipt_number }}
                                        @if ($payment->recorder) · {{ __('recorded by :name', ['name' => $payment->recorder->name]) }} @endif
                                    </span>
                                    @if ($payment->notes)
                                        <span class="mt-1 block whitespace-pre-line text-amber-800">{{ $payment->notes }}</span>
                                    @endif
                                </span>
                                <x-ui.button :href="$documents->receiptUrl($payment, 1)" variant="ghost" size="sm" icon="download" target="_blank" rel="noopener">{{ __('Receipt') }}</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            @if ($suspensions->isNotEmpty())
                <x-ui.card :title="__('Suspension history')" :padding="false">
                    <ul class="divide-y divide-stone-100">
                        @foreach ($suspensions as $suspension)
                            <li class="px-5 py-3 text-sm">
                                <p class="font-semibold text-stone-900">{{ $suspension->reason }}</p>
                                <p class="text-stone-500">
                                    {{ __('Suspended :date by :who', ['date' => $suspension->suspended_at->format('j M Y'), 'who' => $suspension->automatic ? __('the overdue-dues rule') : $suspension->suspender?->name]) }}
                                    @if ($suspension->lifted_at)
                                        · {{ __('lifted :date', ['date' => $suspension->lifted_at->format('j M Y')]) }}{{ $suspension->lifter ? ' '.__('by :name', ['name' => $suspension->lifter->name]) : '' }}
                                    @else
                                        · <span class="font-semibold text-red-700">{{ __('active') }}</span>
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('School')">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-stone-500">{{ __('Proprietor') }}</dt>
                        <dd class="mt-0.5 font-semibold text-stone-900">
                            @forelse ($proprietors as $proprietor)
                                <span class="block">{{ $proprietor->name }} · <a class="text-brand-700 hover:text-brand-800" href="tel:{{ $proprietor->phone }}">{{ \App\Support\PhoneNumber::format($proprietor->phone) }}</a></span>
                            @empty
                                —
                            @endforelse
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">{{ __('CAC / registration number') }}</dt>
                        <dd class="mt-0.5 font-semibold text-stone-900">{{ $school->registration_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">{{ __('Levels · pupils') }}</dt>
                        <dd class="mt-0.5 font-semibold capitalize text-stone-900">{{ collect($school->levels)->implode(', ') ?: '—' }} · {{ number_format((int) $school->pupil_count) }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">{{ __('Address') }}</dt>
                        <dd class="mt-0.5 font-semibold text-stone-900">{{ $school->address ?? '—' }}</dd>
                    </div>
                </dl>
                <div class="mt-5 flex flex-col gap-2">
                    @if ($certificateUrl)
                        <x-ui.button :href="$certificateUrl" variant="secondary" icon="award" target="_blank" rel="noopener">{{ __('Certificate') }}</x-ui.button>
                    @endif
                    @if ($application)
                        <x-ui.button :href="route('membership.applications.show', $application)" variant="ghost" icon="file-text">{{ __('Original application') }}</x-ui.button>
                    @endif
                </div>
            </x-ui.card>

            @can('membership.schools.suspend')
                <x-ui.card :title="__('Standing')">
                    @if ($school->membership_status === \App\Enums\MembershipStatus::Suspended)
                        <form method="POST" action="{{ route('membership.schools.reinstate', $school) }}" class="space-y-3">
                            @csrf
                            <x-ui.input name="note" :label="__('Note (optional)')" />
                            <x-ui.button type="submit" class="w-full" icon="rotate-ccw">{{ __('Reinstate school') }}</x-ui.button>
                            <p class="text-sm text-stone-500">{{ __('The school becomes Active, or Owing if dues are still overdue.') }}</p>
                        </form>
                    @else
                        <p class="text-sm text-stone-600">{{ __('Suspending a school marks it Suspended on the public verify page until it is reinstated.') }}</p>
                        <x-ui.button variant="danger" class="mt-4 w-full" icon="ban" x-on:click="$dispatch('open-modal', 'suspend')">{{ __('Suspend school') }}</x-ui.button>

                        <x-ui.modal name="suspend" :title="__('Suspend :school', ['school' => $school->name])">
                            <form method="POST" action="{{ route('membership.schools.suspend', $school) }}" class="space-y-4">
                                @csrf
                                <x-ui.input name="reason" :label="__('Reason (sent to the proprietor)')" required minlength="5" />
                                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                    <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'suspend')">{{ __('Cancel') }}</x-ui.button>
                                    <x-ui.button type="submit" variant="danger">{{ __('Suspend') }}</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>
                        @error('reason')
                            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'suspend'))"></div>
                        @enderror
                    @endif
                </x-ui.card>
            @endcan
        </div>
    </div>

    @can('membership.dues.record')
        @if ($unpaid->isNotEmpty())
            <x-ui.modal name="record-payment" :title="__('Record a dues payment')">
                <form method="POST" action="{{ route('membership.schools.payments.store', $school) }}" class="space-y-4"
                    x-data="{ amounts: @js($unpaid->pluck('amount_kobo', 'id')), picked: @js(array_map('strval', old('dues', $unpaid->modelKeys()))),
                        get total() { return this.picked.reduce((sum, id) => sum + (this.amounts[id] ?? 0), 0) } }">
                    @csrf
                    <fieldset>
                        <legend class="text-sm font-medium text-stone-800">{{ __('Dues covered') }}</legend>
                        @foreach ($unpaid as $due)
                            <label class="flex min-h-11 cursor-pointer items-center justify-between gap-3 py-1 text-sm">
                                <span class="flex items-center gap-3">
                                    <input type="checkbox" name="dues[]" value="{{ $due->id }}" x-model="picked" class="size-5 rounded-md border-stone-300 text-brand-700 focus:ring-brand-600">
                                    {{ $due->charge->title }}
                                </span>
                                <span class="tabular text-stone-700">{{ \App\Support\Money::format($due->amount_kobo) }}</span>
                            </label>
                        @endforeach
                        @error('dues')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    </fieldset>
                    <p class="rounded-xl bg-stone-50 px-4 py-3 text-sm ring-1 ring-stone-200">
                        {{ __('Amount received') }}: <span class="font-semibold tabular" x-text="'₦' + (total / 100).toLocaleString('en-NG')"></span>
                    </p>
                    <x-ui.select name="method" :label="__('How was it paid?')"
                        :options="collect(\Modules\Membership\Enums\PaymentMethod::offline())->mapWithKeys(fn ($m) => [$m->value => $m->label()])" />
                    <x-ui.input name="reference" :label="__('Teller or transfer reference')" :hint="__('Required for bank payments.')" />
                    <x-ui.input name="paid_on" type="date" :label="__('Date paid')" :value="now()->toDateString()" :max="now()->toDateString()" required />
                    <x-ui.input name="notes" :label="__('Note (optional)')" />
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'record-payment')">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit">{{ __('Record and send receipt') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
            @if ($errors->hasAny(['dues', 'method', 'reference', 'paid_on']))
                <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'record-payment'))"></div>
            @endif
        @endif
    @endcan
</x-layouts.app>
