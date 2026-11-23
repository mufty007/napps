@php
    $editing = $lease->exists;
    $title = $editing ? __('Edit lease') : __('Add a lease');
@endphp

<x-layouts.app :title="$title">
    <x-ui.page-header
        :eyebrow="__('Premises')"
        :title="$title"
        :description="$editing ? __('Update the landlord, rent or dates. Status changes that follow a dispute happen on the dispute itself.') : __('Add your school premises lease so we can remind you before it ends.')"
    >
        <x-slot:actions>
            <x-ui.button :href="$editing ? route('leases.show', $lease) : route('leases.index')" variant="secondary" icon="arrow-left">
                {{ $editing ? __('Back to lease') : __('All leases') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form
        method="POST"
        action="{{ $editing ? route('leases.update', $lease) : route('leases.store') }}"
        class="mx-auto max-w-2xl space-y-6"
    >
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card :title="__('Property')">
            <div class="space-y-5">
                @if ($school)
                    <div class="rounded-xl bg-stone-50 p-4 text-sm ring-1 ring-stone-200">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ __('School') }}</p>
                        <p class="mt-1 font-semibold text-stone-900">{{ $school->name }}</p>
                    </div>
                @elseif ($editing)
                    <div class="rounded-xl bg-stone-50 p-4 text-sm ring-1 ring-stone-200">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ __('School') }}</p>
                        <p class="mt-1 font-semibold text-stone-900">{{ $lease->school?->name }}</p>
                    </div>
                @else
                    <x-ui.select name="school_id" :label="__('School')" :options="$schools" :placeholder="__('Choose a school')" required />
                @endif

                <div class="space-y-1.5">
                    <label for="property_address" class="block text-sm font-medium text-stone-800">{{ __('Property address') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <textarea id="property_address" name="property_address" rows="3" maxlength="2000" required
                        class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 placeholder:text-stone-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm"
                        placeholder="{{ __('Street, area, city') }}">{{ old('property_address', $lease->property_address) }}</textarea>
                    @error('property_address')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Landlord')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="landlord_name" :label="__('Name')" :value="$lease->landlord_name" class="sm:col-span-2" required autocomplete="off" />
                <x-ui.input name="landlord_phone" type="tel" inputmode="tel" :label="__('Phone')" :value="$lease->landlord_phone" placeholder="0803 123 4567" class="sm:col-span-2" autocomplete="tel" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Rent and dates')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="start_date" type="date" :label="__('Start date')" :value="old('start_date', $lease->start_date?->format('Y-m-d'))" required />
                <x-ui.input name="end_date" type="date" :label="__('End date')" :value="old('end_date', $lease->end_date?->format('Y-m-d'))" required />
                <x-ui.input
                    name="rent"
                    inputmode="decimal"
                    :label="__('Rent')"
                    prefix="₦"
                    :value="old('rent', $lease->exists ? \App\Support\Money::toNaira($lease->rent_kobo) : null)"
                    :hint="__('Amount for one payment period')"
                    required
                />
                <x-ui.select
                    name="rent_frequency"
                    :label="__('Paid')"
                    :options="$frequencies"
                    :value="old('rent_frequency', $lease->rent_frequency?->value ?? 'yearly')"
                    required
                />
                @if ($editing && $lease->status === \Modules\Leases\Enums\LeaseStatus::Disputed)
                    <div class="sm:col-span-2 rounded-xl bg-stone-50 p-4 text-sm ring-1 ring-stone-200">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ __('Status') }}</p>
                        <p class="mt-1"><x-ui.badge :tone="$lease->status->tone()" dot>{{ $lease->status->label() }}</x-ui.badge></p>
                        <p class="mt-2 text-stone-600">{{ __('In dispute stays until the dispute is resolved.') }}</p>
                    </div>
                @elseif ($editing)
                    <x-ui.select
                        name="status"
                        :label="__('Status')"
                        :options="$statuses"
                        :value="old('status', $lease->status?->value)"
                        class="sm:col-span-2"
                    />
                @endif
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Notes')" :description="__('Optional. Anything the next person looking at this lease should know.')">
            <textarea id="notes" name="notes" rows="4" maxlength="5000"
                class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 placeholder:text-stone-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm"
                placeholder="{{ __('e.g. rent includes generator, or notice period') }}">{{ old('notes', $lease->notes) }}</textarea>
            @error('notes')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
        </x-ui.card>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button :href="$editing ? route('leases.show', $lease) : route('leases.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" icon-right="check">{{ $editing ? __('Save changes') : __('Add lease') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
