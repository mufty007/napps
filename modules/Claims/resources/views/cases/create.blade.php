<x-layouts.app :title="__('Open a claim')">
    <x-ui.page-header
        :eyebrow="__('Legal')"
        :title="__('Open a claim')"
        :description="__('Start a debt claim for fees a family has not paid. The secretariat will assign a panel lawyer.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('claims.index')" variant="secondary" icon="arrow-left">{{ __('All claims') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @unless ($enabled)
        <x-ui.alert tone="warning">
            {{ __('Debt claims are not switched on for :state yet. Ask the NAPPS secretariat to enable them.', ['state' => $school?->state?->name ?? __('your state')]) }}
        </x-ui.alert>
    @else
        <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <x-ui.card :title="__('Claim details')">
                <form method="POST" action="{{ route('claims.store') }}" class="space-y-5">
                    @csrf

                    @if ($school)
                        <div class="rounded-xl bg-stone-50 p-4 text-sm ring-1 ring-stone-200">
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ __('School') }}</p>
                            <p class="mt-1 font-semibold text-stone-900">{{ $school->name }}</p>
                        </div>
                    @else
                        <x-ui.select name="school_id" :label="__('School')" :options="$schools" :placeholder="__('Choose a school')" required />
                    @endif

                    <x-ui.input name="debtor_name" :label="__('Debtor (parent or guardian)')" required autocomplete="off" />
                    <x-ui.input name="debtor_phone" type="tel" inputmode="tel" :label="__('Debtor phone')" :hint="__('Used to send the demand letter and reminders.')" autocomplete="off" />

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input name="amount" inputmode="decimal" :label="__('Amount owed')" prefix="₦" :hint="__('In naira, e.g. 485000')" required />
                        <x-ui.input name="days_overdue" type="number" min="1" max="3650" :label="__('Days overdue')" :value="90" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="summary" class="block text-sm font-medium text-stone-800">{{ __('What happened') }}</label>
                        <textarea id="summary" name="summary" rows="5" maxlength="5000"
                            class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm"
                            placeholder="{{ __('Which terms are unpaid, what reminders were sent, any agreement with the family.') }}">{{ old('summary') }}</textarea>
                        @error('summary')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <x-ui.button type="submit" icon-right="arrow-right">{{ __('Open claim') }}</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.side-panel :title="__('What happens next')">
                <ol class="space-y-4 text-sm text-stone-700">
                    <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xs font-bold text-white">1</span><span>{{ __('Add the signed fee agreement, invoices and reminders to the claim pack.') }}</span></li>
                    <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xs font-bold text-white">2</span><span>{{ __('Generate the demand letter from the template library and send it.') }}</span></li>
                    <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xs font-bold text-white">3</span><span>{{ __('If it is not settled, the chapter mediates, then a panel lawyer takes it to court.') }}</span></li>
                </ol>
            </x-ui.side-panel>
        </div>
    @endunless
</x-layouts.app>
