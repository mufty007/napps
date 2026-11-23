<x-layouts.app :title="__('Request clearance')">
    <x-ui.page-header
        :eyebrow="__('Records')"
        :title="__('Request transfer clearance')"
        :description="__('Ask the pupil\'s previous school to confirm there is nothing outstanding.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('records.transfers.index', ['box' => 'outgoing'])" variant="secondary" icon="arrow-left">{{ __('All transfers') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <x-ui.card :title="__('Who is moving?')">
            <form method="POST" action="{{ route('records.transfers.store') }}" class="space-y-5">
                @csrf
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="first_name" :label="__('Pupil\'s first name')" required autocomplete="off" />
                    <x-ui.input name="last_name" :label="__('Pupil\'s last name')" required autocomplete="off" />
                </div>
                <x-ui.input name="guardian_phone" type="tel" inputmode="tel" :label="__('Guardian\'s phone number')" :hint="__('The number the previous school has on file. It is how we match the right pupil.')" required autocomplete="off" />
                <x-ui.button type="submit" icon-right="arrow-right">{{ __('Send request') }}</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.side-panel :title="__('What happens next')">
            <ol class="space-y-4 text-sm text-stone-700">
                <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xs font-bold text-white">1</span><span>{{ __('The school that holds the record gets your request.') }}</span></li>
                <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xs font-bold text-white">2</span><span>{{ __('It has :days working days to clear or block the transfer, and say what is owed.', ['days' => $sla]) }}</span></li>
                <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xs font-bold text-white">3</span><span>{{ __('You see only its answer, never the pupil\'s record.') }}</span></li>
            </ol>
        </x-ui.side-panel>
    </div>
</x-layouts.app>
