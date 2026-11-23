{{-- Dashboard card for executives and the secretariat. Fed by Modules\Claims\Support\ClaimsOverviewWidget. --}}
<div class="mt-6" x-reveal>
    <x-ui.card :title="__('Debt claims')" :description="__('Claims from schools in your area.')">
        <x-slot:actions>
            <x-ui.button :href="route('claims.index')" variant="ghost" size="sm" icon-right="arrow-right">{{ __('Open') }}</x-ui.button>
        </x-slot:actions>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat :label="__('Open claims')" :value="$overview['open']" icon="scale" :href="route('claims.index')" />
            <x-ui.stat :label="__('In court')" :value="$overview['in_court']" icon="gavel" :href="route('claims.index', ['status' => 'open', 'stage' => 'filed'])" />
            <x-ui.stat :label="__('Hearings this week')" :value="$overview['hearings']" icon="calendar" />
            <x-ui.stat :label="__('Recovered')" :value="intdiv($overview['recovered_kobo'], 100)" format="currency" icon="hand-coins" />
        </div>
    </x-ui.card>
</div>
