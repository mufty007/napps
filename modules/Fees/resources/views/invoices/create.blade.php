@php
    $textarea = 'block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 placeholder:text-stone-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm';
    $totals = $structures->mapWithKeys(fn ($s) => [$s->id => $s->totalKobo() / 100]);
@endphp

<x-layouts.app :title="__('Raise invoices')">
    <x-ui.page-header
        :eyebrow="__('Fees')"
        :title="__('Raise invoices')"
        :description="__('Bill a whole class at once. Each child gets an invoice from the fee structure you choose.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('fees.invoices.index')" variant="secondary" icon="arrow-left">{{ __('Invoices') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($structures->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="list-checks" :title="__('Set up a fee structure first')" :description="__('Invoices are raised from a fee structure: the list of charges for a term.')">
                @if ($canManageStructures)
                    <x-ui.button :href="route('fees.structures.create')" icon="plus">{{ __('New structure') }}</x-ui.button>
                @else
                    <p class="text-sm text-stone-500">{{ __('Ask the proprietor to add one.') }}</p>
                @endif
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <form method="POST" action="{{ route('fees.invoices.store') }}" class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]"
            x-data="{
                structure: @js((string) old('fee_structure_id', $structures->first()->id)),
                totals: @js($totals),
                lines: 0,
                count() { return this.$refs.students.value.split(/\r?\n/).filter(line => line.trim() !== '').length },
            }"
            x-init="lines = count()">
            @csrf

            <div class="min-w-0 space-y-6">
                <x-ui.card :title="__('Who to bill')">
                    <div class="space-y-1.5">
                        <label for="students" class="block text-sm font-medium text-stone-800">{{ __('Children') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                        <textarea id="students" name="students" rows="10" x-ref="students" @input="lines = count()" required
                            class="{{ $textarea }} font-mono" placeholder="Amina Bello, Hajiya Bello, 08031234567&#10;Yusuf Bello, Hajiya Bello, 08031234567&#10;Zainab Musa, Mal. Musa, 08057654321">{{ old('students') }}</textarea>
                        <p class="text-sm text-stone-500">{{ __('One child per line: name, parent\'s name, parent\'s phone. Parents already enrolled by their chapter are linked automatically and can pay online.') }}</p>
                        @error('students')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    </div>
                </x-ui.card>
            </div>

            <div class="min-w-0 space-y-6">
                <x-ui.card :title="__('Invoice details')">
                    <div class="space-y-5">
                        <div class="space-y-1.5">
                            <x-ui.select name="fee_structure_id" :label="__('Fee structure')" :value="old('fee_structure_id')" x-model="structure"
                                :options="$structures->mapWithKeys(fn ($s) => [$s->id => $s->label()])" />
                            <p class="text-sm text-stone-500">
                                {{ __('Per child') }}:
                                <span class="font-semibold text-stone-900 tabular" x-text="'₦' + Number(totals[structure] ?? 0).toLocaleString('en-NG', { maximumFractionDigits: 2 })"></span>
                                · <span x-text="lines"></span> {{ __('children') }}
                            </p>
                        </div>
                        <x-ui.input name="due_date" type="date" :label="__('Due date')" :value="now()->addDays(14)->toDateString()" required />
                        <div class="space-y-1.5">
                            <label for="notes" class="block text-sm font-medium text-stone-800">{{ __('Note on the invoice (optional)') }}</label>
                            <textarea id="notes" name="notes" rows="2" maxlength="2000" class="{{ $textarea }}">{{ old('notes') }}</textarea>
                            @error('notes')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <x-ui.checkbox name="issue" :label="__('Issue now and notify parents by SMS')" :checked="true" />
                    </div>
                </x-ui.card>

                <x-ui.button type="submit" size="lg" class="w-full" icon="send">{{ __('Raise invoices') }}</x-ui.button>
            </div>
        </form>
    @endif
</x-layouts.app>
