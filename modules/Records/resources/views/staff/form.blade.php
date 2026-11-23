@php
    $editing = $member->exists;
    $title = $editing ? __('Edit :name', ['name' => $member->name]) : __('Add staff member');
@endphp
<x-layouts.app :title="$title">
    <x-ui.page-header :eyebrow="__('Records')" :title="$title" :description="__('Record who works at the school, their role and when they started or left.')">
        <x-slot:actions>
            <x-ui.button :href="route('records.staff.index')" variant="secondary" icon="arrow-left">{{ __('All staff') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="max-w-2xl space-y-6">
        <x-ui.card :title="__('Staff details')">
            <form method="POST" action="{{ $editing ? route('records.staff.update', $member) : route('records.staff.store') }}" class="space-y-5">
                @csrf
                @if ($editing) @method('PUT') @endif

                @if (! $editing && $schools->isNotEmpty())
                    <x-ui.select name="school_id" :label="__('School')" :options="$schools" :placeholder="__('Choose a school')" required />
                @endif

                <x-ui.input name="name" :label="__('Full name')" :value="$member->name" required autocomplete="off" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="role_title" :label="__('Role or job title')" :value="$member->role_title" :placeholder="__('e.g. Class teacher, Head teacher')" required autocomplete="off" />
                    <x-ui.input name="phone" type="tel" inputmode="tel" :label="__('Phone')" :value="$member->phone" autocomplete="off" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="started_at" type="date" :label="__('Started on')" :value="$member->started_at?->toDateString()" />
                    <x-ui.input name="left_at" type="date" :label="__('Left on')" :value="$member->left_at?->toDateString()" :hint="__('Leave empty while they still work here.')" />
                </div>

                <x-ui.button type="submit" icon-right="check">{{ $editing ? __('Save changes') : __('Add to register') }}</x-ui.button>
            </form>
        </x-ui.card>

        @if ($editing)
            <x-ui.card :title="__('Remove from register')" :description="__('Use “Left on” to keep history. Removing erases this entry (the audit log keeps a trace).')">
                <form method="POST" action="{{ route('records.staff.destroy', $member) }}" onsubmit="return confirm('{{ __('Remove this person from the register?') }}')">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="secondary" icon="trash-2">{{ __('Remove') }}</x-ui.button>
                </form>
            </x-ui.card>
        @endif
    </div>
</x-layouts.app>
