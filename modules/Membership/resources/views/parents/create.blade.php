<x-layouts.app :title="__('Add parent')">
    <x-ui.page-header
        :title="__('Add a parent')"
        :description="__('We create their account (or link an existing one) and grant the Parent role. They sign in with this phone number — they do not register themselves.')"
    />

    <form method="POST" action="{{ route('membership.parents.store') }}" class="mx-auto max-w-lg space-y-5">
        @csrf
        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input name="name" :label="__('Full name')" required autofocus />
                <x-ui.input name="phone" type="tel" inputmode="tel" :label="__('Phone number')" placeholder="0803 123 4567" required />
                <x-ui.input name="email" type="email" :label="__('Email (optional)')" />
                <x-ui.input name="notes" :label="__('Notes (optional)')" :hint="__('e.g. Guardian of pupils at Al-Ihsan')" />
            </div>
        </x-ui.card>
        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" icon-right="check">{{ __('Add parent') }}</x-ui.button>
            <x-ui.button :href="route('membership.parents.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
