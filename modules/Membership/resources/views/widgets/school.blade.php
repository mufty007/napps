@if ($membershipSchool && $membershipSchool->membership_status->isMember())
    @php($school = $membershipSchool)
    <x-ui.card :title="__('NAPPS membership')" class="mb-6" x-reveal>
        <x-slot:actions>
            <x-ui.badge :tone="$school->membership_status->tone()" dot>{{ $school->membership_status->label() }}</x-ui.badge>
        </x-slot:actions>

        @if ($suspension)
            <x-ui.alert tone="danger" :title="__('Membership suspended')" class="mb-5">
                {{ $suspension->reason }}. {{ __('Contact your chapter executive to resolve it.') }}
            </x-ui.alert>
        @endif

        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                @if ($outstandingKobo > 0)
                    <p class="text-sm text-stone-500">{{ __('Dues outstanding') }}</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 tabular">{{ \App\Support\Money::format($outstandingKobo) }}</p>
                    <p @class(['mt-1 text-sm', 'font-semibold text-red-700' => $overdueCount > 0, 'text-stone-500' => $overdueCount === 0])>
                        @if ($overdueCount > 0)
                            {{ __('Overdue since :date', ['date' => $nextDueDate?->format('j M Y')]) }}
                        @else
                            {{ __('Due by :date', ['date' => $nextDueDate?->format('j M Y')]) }}
                        @endif
                    </p>
                @else
                    <p class="flex items-center gap-2 text-base font-semibold text-emerald-800">
                        <x-ui.icon name="circle-check" class="text-emerald-600" />
                        {{ __('Dues paid up') }}
                    </p>
                    <p class="mt-1 text-sm text-stone-500">
                        @if ($lastPayment)
                            {{ __('Last paid :amount on :date', ['amount' => \App\Support\Money::format($lastPayment->amount_kobo), 'date' => $lastPayment->paid_at->format('j M Y')]) }}
                        @else
                            {{ __('No dues have been issued to your school yet.') }}
                        @endif
                    </p>
                @endif
                <p class="mt-3 text-sm text-stone-500">{{ __('Membership number') }}: <span class="font-semibold text-stone-800">{{ $school->membership_number ?? '—' }}</span></p>
            </div>

            <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                @if ($canPayDues && $outstandingKobo > 0)
                    <form method="POST" action="{{ route('membership.dues.pay') }}" class="w-full sm:w-auto">
                        @csrf
                        <x-ui.button type="submit" class="w-full" icon="credit-card">{{ __('Pay :amount', ['amount' => \App\Support\Money::format($outstandingKobo)]) }}</x-ui.button>
                    </form>
                @endif
                @if ($canPayDues)
                    <x-ui.button :href="route('membership.dues.index')" variant="secondary" class="flex-1 sm:flex-none">{{ __('Dues & receipts') }}</x-ui.button>
                @endif
                @if ($certificateUrl)
                    <x-ui.button :href="$certificateUrl" variant="ghost" icon="award" class="flex-1 sm:flex-none" target="_blank" rel="noopener">{{ __('Certificate') }}</x-ui.button>
                @endif
            </div>
        </div>
    </x-ui.card>
@endif
