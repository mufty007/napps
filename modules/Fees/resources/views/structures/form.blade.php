@php
    $editing = $structure->exists;
    $rows = collect($items)->map(fn ($item) => ['name' => (string) ($item['name'] ?? ''), 'amount' => (string) ($item['amount'] ?? '')])->values();
@endphp

<x-layouts.app :title="$editing ? __('Edit fee structure') : __('New fee structure')">
    <x-ui.page-header
        :eyebrow="__('Fees')"
        :title="$editing ? __('Edit :name', ['name' => $structure->name]) : __('New fee structure')"
        :description="__('List what a child pays for a term. Invoices keep the amounts they were raised with.')"
    />

    <form
        method="POST"
        action="{{ $editing ? route('fees.structures.update', $structure) : route('fees.structures.store') }}"
        class="mx-auto max-w-2xl space-y-6"
        x-data="{
            items: @js($rows),
            add() { this.items.push({ name: '', amount: '' }) },
            remove(i) { this.items.splice(i, 1) },
            total() { return this.items.reduce((sum, row) => sum + (parseFloat(String(row.amount).replace(/[^0-9.]/g, '')) || 0), 0) },
        }"
    >
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="name" :label="__('Name')" :value="$structure->name" :placeholder="__('e.g. Primary 1–3')" class="sm:col-span-2" required />
                <x-ui.input name="academic_year" :label="__('Academic year')" :value="$structure->academic_year" placeholder="2026/2027" required />
                <x-ui.input name="term" :label="__('Term (optional)')" :value="$structure->term" :placeholder="__('First term')" />
                <x-ui.checkbox name="active" :label="__('Active: available when raising invoices')" :checked="$structure->active" class="sm:col-span-2" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Fee items')" :description="__('One line per charge: tuition, uniform, transport...')">
            @error('items')<p class="mb-3 text-sm font-medium text-red-700">{{ $message }}</p>@enderror

            <div class="space-y-3">
                <template x-for="(row, i) in items" :key="i">
                    <div class="grid grid-cols-[minmax(0,1fr)_7.5rem_2.75rem] items-start gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_2.75rem]">
                        <div>
                            <label class="sr-only" :for="'item-name-' + i">{{ __('Item name') }}</label>
                            <input type="text" :id="'item-name-' + i" :name="'items[' + i + '][name]'" x-model="row.name" maxlength="255" placeholder="{{ __('Item name') }}" required
                                class="block w-full min-h-11 rounded-xl border-0 bg-white px-3.5 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 placeholder:text-stone-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">
                        </div>
                        <div class="relative">
                            <label class="sr-only" :for="'item-amount-' + i">{{ __('Amount in naira') }}</label>
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-stone-500">₦</span>
                            <input type="text" inputmode="decimal" :id="'item-amount-' + i" :name="'items[' + i + '][amount]'" x-model="row.amount" placeholder="0" required
                                class="block w-full min-h-11 rounded-xl border-0 bg-white py-2.5 pl-8 pr-3 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 placeholder:text-stone-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">
                        </div>
                        <button type="button" @click="remove(i)" x-show="items.length > 1" class="flex min-h-11 w-11 items-center justify-center rounded-xl text-stone-500 hover:bg-stone-100 hover:text-red-700" aria-label="{{ __('Remove item') }}">
                            <x-ui.icon name="trash-2" />
                        </button>
                    </div>
                </template>
            </div>

            @foreach ($errors->getMessages() as $key => $messages)
                @if (str_starts_with($key, 'items.'))
                    <p class="mt-2 text-sm font-medium text-red-700">{{ $messages[0] }}</p>
                @endif
            @endforeach

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 pt-4">
                <x-ui.button type="button" variant="secondary" icon="plus" @click="add()">{{ __('Add item') }}</x-ui.button>
                <p class="text-sm text-stone-600">{{ __('Per child') }}: <span class="ml-1 text-lg font-semibold text-stone-900 tabular" x-text="'₦' + total().toLocaleString('en-NG', { maximumFractionDigits: 2 })"></span></p>
            </div>
        </x-ui.card>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                @if ($editing)
                    <button type="submit" form="delete-structure" class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold text-red-700 hover:bg-red-50"
                        onclick="return confirm('{{ __('Delete this fee structure? If it has invoices it is switched off instead.') }}')">
                        <x-ui.icon name="trash-2" class="size-4" /> {{ __('Delete') }}
                    </button>
                @endif
            </div>
            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                <x-ui.button :href="route('fees.structures.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit">{{ $editing ? __('Save changes') : __('Save structure') }}</x-ui.button>
            </div>
        </div>
    </form>

    @if ($editing)
        <form id="delete-structure" method="POST" action="{{ route('fees.structures.destroy', $structure) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif
</x-layouts.app>
