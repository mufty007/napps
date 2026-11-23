@php
    use App\Support\Money;
    $display = $invoice->displayStatus();
    $lines = $invoice->lines ?? [];
    $days = $invoice->daysOverdue();
@endphp

<x-layouts.app :title="$invoice->reference">
    <x-ui.page-header
        :eyebrow="$invoice->reference"
        :title="$invoice->student_name"
        :description="__(':school · due :date', ['school' => $school?->name, 'date' => $invoice->due_date->format('j M Y')])"
    >
        <x-slot:actions>
            <x-ui.badge :tone="$display->tone()" dot class="text-sm">{{ $display->label() }}</x-ui.badge>
            <x-ui.button :href="route('fees.invoices.index')" variant="secondary" icon="arrow-left">{{ __('Invoices') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            @if ($invoice->status === \Modules\Fees\Enums\InvoiceStatus::Draft)
                <x-ui.alert tone="info" :title="__('Draft')">
                    {{ __('The family has not been told about this invoice yet.') }}
                    @if ($canManage)
                        <form method="POST" action="{{ route('fees.invoices.issue', $invoice) }}" class="mt-3">
                            @csrf
                            <x-ui.button type="submit" icon="send" size="sm">{{ __('Issue and notify') }}</x-ui.button>
                        </form>
                    @endif
                </x-ui.alert>
            @elseif ($days > 0)
                <x-ui.alert tone="danger" :title="trans_choice(':count day overdue|:count days overdue', $days)">
                    {{ __(':amount is still owed.', ['amount' => Money::format($invoice->balanceKobo())]) }}
                    @if ($invoice->isEscalated())
                        {{ __('Claim :ref is open for this debt.', ['ref' => $invoice->claim_reference]) }}
                    @endif
                </x-ui.alert>
            @endif

            <x-ui.card :title="__('Charges')">
                @if ($lines !== [])
                    <ul class="divide-y divide-stone-100 text-sm">
                        @foreach ($lines as $line)
                            <li class="flex items-center justify-between gap-3 py-2.5">
                                <span class="text-stone-700">{{ $line['name'] }}</span>
                                <span class="font-semibold tabular text-stone-900">{{ Money::format($line['amount_kobo']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <dl class="@if ($lines !== []) mt-3 border-t border-stone-200 pt-3 @endif space-y-1.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-stone-500">{{ __('Total') }}</dt><dd class="font-semibold tabular text-stone-900">{{ Money::format($invoice->total_kobo) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-stone-500">{{ __('Paid') }}</dt><dd class="font-semibold tabular text-emerald-700">{{ Money::format($invoice->paid_kobo) }}</dd></div>
                    <div class="flex justify-between gap-3 text-base"><dt class="font-medium text-stone-800">{{ __('Balance') }}</dt><dd class="font-semibold tabular text-stone-900">{{ Money::format($invoice->balanceKobo()) }}</dd></div>
                </dl>
                @if ($invoice->notes)
                    <p class="mt-4 whitespace-pre-wrap rounded-xl bg-stone-50 p-3 text-sm text-stone-700 ring-1 ring-stone-200">{{ $invoice->notes }}</p>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Payments')" :padding="$invoice->payments->isEmpty()">
                @if ($invoice->payments->isEmpty())
                    <x-ui.empty-state icon="wallet" :title="__('No payments yet')" :description="__('Online payments and cash you record appear here.')" class="py-6" />
                @else
                    <x-ui.table flush>
                        <x-slot:head>
                            <th scope="col">{{ __('Date') }}</th>
                            <th scope="col">{{ __('Method') }}</th>
                            <th scope="col" class="hidden sm:table-cell">{{ __('Reference') }}</th>
                            <th scope="col" class="text-right">{{ __('Amount') }}</th>
                        </x-slot:head>
                        @foreach ($invoice->payments as $payment)
                            <tr>
                                <td class="whitespace-nowrap tabular">{{ $payment->paid_at->format('j M Y') }}</td>
                                <td>
                                    {{ $payment->method->label() }}
                                    @if ($payment->recorder)<span class="block text-xs text-stone-500">{{ __('by :name', ['name' => $payment->recorder->name]) }}</span>@endif
                                    @if (data_get($payment->meta, 'refund_due'))<span class="block text-xs font-semibold text-amber-700">{{ __(':amount to refund', ['amount' => Money::format((int) data_get($payment->meta, 'excess_kobo'))]) }}</span>@endif
                                </td>
                                <td class="hidden max-w-[10rem] truncate sm:table-cell">{{ $payment->reference ?? '—' }}</td>
                                <td class="whitespace-nowrap text-right font-semibold tabular text-stone-900">{{ Money::format($payment->amount_kobo) }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>

            @if ($canRecord)
                <x-ui.card :title="__('Record a payment')" :description="__('Cash, bank transfer or teller deposit received at school.')">
                    <form method="POST" action="{{ route('fees.invoices.payments.store', $invoice) }}" class="grid gap-5 sm:grid-cols-2">
                        @csrf
                        <x-ui.input name="amount" inputmode="decimal" :label="__('Amount received')" prefix="₦" :value="Money::toNaira($invoice->balanceKobo())" required />
                        <x-ui.select name="method" :label="__('Method')" :options="$methods" />
                        <x-ui.input name="reference" :label="__('Teller or transfer number')" :hint="__('Optional')" maxlength="64" autocomplete="off" />
                        <x-ui.input name="paid_at" type="date" :label="__('Date received')" :value="now()->toDateString()" :max="now()->toDateString()" />
                        <x-ui.input name="note" :label="__('Note (optional)')" maxlength="500" class="sm:col-span-2" />
                        <div class="sm:col-span-2">
                            <x-ui.button type="submit" icon="check">{{ __('Record payment') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endif
        </div>

        <div class="min-w-0 space-y-6">
            <x-ui.side-panel :title="__('Parent')">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-stone-500">{{ __('Name') }}</dt>
                        <dd class="mt-0.5 font-semibold text-stone-900">{{ $invoice->guardian_name ?? $invoice->guardian?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">{{ __('Phone') }}</dt>
                        <dd class="mt-0.5 font-semibold text-stone-900">{{ $invoice->guardian_phone ? \App\Support\PhoneNumber::format($invoice->guardian_phone) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">{{ __('Online payment') }}</dt>
                        <dd class="mt-0.5 font-semibold text-stone-900">{{ $invoice->guardian ? __('Parent account linked') : __('Not linked: parent must be enrolled by their chapter') }}</dd>
                    </div>
                </dl>
            </x-ui.side-panel>

            @if ($canManage && ($invoice->isOpen() || $invoice->status === \Modules\Fees\Enums\InvoiceStatus::Draft || $invoice->paid_kobo === 0))
                <x-ui.side-panel :title="__('Actions')">
                    <div class="space-y-3">
                        @if ($invoice->isOpen())
                            <form method="POST" action="{{ route('fees.invoices.remind', $invoice) }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" icon="bell-ring" class="w-full" :disabled="! $remindersAllowed">{{ __('Send reminder now') }}</x-ui.button>
                            </form>
                            @unless ($remindersAllowed)
                                <p class="text-xs text-stone-500">{{ __('This parent did not consent to SMS reminders.') }}</p>
                            @endunless
                        @endif

                        @if ($canEscalate)
                            <form method="POST" action="{{ route('fees.invoices.escalate', $invoice) }}" onsubmit="return confirm('{{ __('Open a debt claim for the balance?') }}')">
                                @csrf
                                <x-ui.button type="submit" variant="danger" icon="scale" class="w-full">{{ __('Escalate to a claim') }}</x-ui.button>
                            </form>
                        @elseif ($invoice->isEscalated())
                            <p class="flex items-center gap-2 text-sm text-stone-700"><x-ui.icon name="scale" class="size-4" /> {{ __('Claim :ref', ['ref' => $invoice->claim_reference]) }}</p>
                        @endif

                        @if ($invoice->paid_kobo === 0 && $invoice->status !== \Modules\Fees\Enums\InvoiceStatus::Void)
                            <form method="POST" action="{{ route('fees.invoices.void', $invoice) }}" onsubmit="return confirm('{{ __('Void this invoice? The family will no longer owe it.') }}')">
                                @csrf
                                <x-ui.button type="submit" variant="ghost" icon="ban" class="w-full">{{ __('Void invoice') }}</x-ui.button>
                            </form>
                        @endif
                    </div>
                </x-ui.side-panel>
            @endif

            <x-ui.side-panel :title="__('Reminders sent')">
                @if ($invoice->reminders->isEmpty())
                    <p class="text-sm text-stone-500">{{ __('No reminders yet. They go out before the due date, on it, and 7, 14 and 30 days after.') }}</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($invoice->reminders as $reminder)
                            <li class="flex items-center justify-between gap-3">
                                <span class="text-stone-700">{{ $reminder->kind->label() }}</span>
                                <span class="tabular text-stone-500">{{ $reminder->sent_at->format('j M, g:ia') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.side-panel>
        </div>
    </div>
</x-layouts.app>
