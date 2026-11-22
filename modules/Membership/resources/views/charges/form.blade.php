@php
    $editing = $charge->exists;
    $splitLevels = match ($level) {
        \App\Enums\ScopeLevel::National => ['share_national' => __('National'), 'share_state' => __('State'), 'share_chapter' => __('Chapter')],
        \App\Enums\ScopeLevel::State => ['share_state' => __('State'), 'share_chapter' => __('Chapter')],
        default => [],
    };
@endphp

<x-layouts.app :title="$editing ? __('Edit charge') : __('New charge')">
    <x-ui.page-header
        :eyebrow="$place ? __('Set by :place', ['place' => $place]) : __('Set by national')"
        :title="$editing ? __('Edit :title', ['title' => $charge->title]) : __('New dues or levy')"
        :description="__('Saved as a draft first. Nothing is billed until you issue it.')"
    />

    <form method="POST" action="{{ $editing ? route('membership.charges.update', $charge) : route('membership.charges.store') }}" class="mx-auto max-w-2xl space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="title" :label="__('Title')" :value="$charge->title" :placeholder="__('e.g. Annual dues 2027')" class="sm:col-span-2" required />
                <x-ui.select name="kind" :label="__('Type')" :value="$charge->kind?->value"
                    :options="collect(\Modules\Membership\Enums\DuesKind::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label()])" />
                <x-ui.select name="year" :label="__('Dues year')" :value="$charge->year"
                    :options="collect(range(now()->year - 1, now()->year + 2))->mapWithKeys(fn ($y) => [$y => $y])" />
                <x-ui.input name="amount" :label="__('Amount per school')" prefix="₦" inputmode="decimal" :value="\App\Support\Money::toNaira($charge->amount_kobo)" placeholder="25,000" required />
                <x-ui.input name="due_date" type="date" :label="__('Due date')" :value="$charge->due_date?->toDateString()" required :hint="__('Schools become Owing after this date.')" />
                <div class="space-y-1.5 sm:col-span-2">
                    <label for="description" class="block text-sm font-medium text-stone-800">{{ __('Note to schools (optional)') }}</label>
                    <textarea id="description" name="description" rows="3" class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('description', $charge->description) }}</textarea>
                    @error('description')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-ui.card>

        @if ($splitLevels)
            <x-ui.card :title="__('Split')" :description="__('How each payment is shared between levels. Leave 100% on your own level for no split.')"
                x-data="{ shares: @js(collect($splitLevels)->keys()->mapWithKeys(fn ($key) => [$key => (int) old($key, $charge->{$key})])) }">
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ($splitLevels as $key => $label)
                        <x-ui.input :name="$key" type="number" min="0" max="100" inputmode="numeric" :label="$label.' %'" :value="(int) $charge->{$key}" x-model.number="shares['{{ $key }}']" />
                    @endforeach
                </div>
                <p class="mt-3 text-sm" :class="Object.values(shares).reduce((a, b) => a + (Number(b) || 0), 0) === 100 ? 'text-stone-500' : 'font-semibold text-red-700'">
                    {{ __('Total') }}: <span x-text="Object.values(shares).reduce((a, b) => a + (Number(b) || 0), 0)"></span>%
                </p>
            </x-ui.card>
        @endif

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button :href="$editing ? route('membership.charges.show', $charge) : route('membership.charges.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit">{{ $editing ? __('Save changes') : __('Save draft') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
