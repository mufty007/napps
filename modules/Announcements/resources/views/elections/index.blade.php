<x-layouts.app :title="__('Elections')">
    <x-ui.page-header
        :eyebrow="$school?->name ?? __('Association')"
        :title="__('Elections')"
        :description="$manager
            ? __('Run chapter and state elections. Every active member school has exactly one vote.')
            : __('Elections open to your school. Your school has one vote in each.')"
    >
        @if ($manager)
            <x-slot:actions>
                <x-ui.button :href="route('elections.create')" icon="plus">{{ __('New election') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-5">{{ $errors->first() }}</x-ui.alert>
    @endif

    @if ($elections->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="vote" :title="__('No elections yet')" :description="$manager ? __('Create an election, add candidates and open voting.') : __('When your chapter or state opens an election, it will appear here.')">
                @if ($manager)
                    <x-ui.button :href="route('elections.create')" icon="plus">{{ __('New election') }}</x-ui.button>
                @endif
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <ul class="grid gap-4 sm:grid-cols-2">
            @foreach ($elections as $election)
                <li>
                    <x-ui.card class="flex h-full flex-col">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge :tone="$election->statusTone()" dot>{{ $election->statusLabel() }}</x-ui.badge>
                            <x-ui.badge tone="brand">{{ $election->electorateLabel() }}</x-ui.badge>
                            @if (! $manager && $election->isOpen())
                                @if ($voted->has($election->id))
                                    <x-ui.badge tone="success">{{ __('Your school has voted') }}</x-ui.badge>
                                @else
                                    <x-ui.badge tone="warning">{{ __('Vote needed') }}</x-ui.badge>
                                @endif
                            @endif
                        </div>
                        <h2 class="mt-3 text-base font-semibold text-stone-900">{{ $election->title }}</h2>
                        <p class="mt-1 flex-1 text-sm text-stone-500">
                            {{ trans_choice(':count candidate|:count candidates', $election->candidates_count) }}
                            · {{ trans_choice(':count vote cast|:count votes cast', $election->votes_count) }}
                            @if ($election->closes_at) · {{ __('Closes :date', ['date' => $election->closes_at->timezone(config('app.timezone'))->format('j M Y, g:ia')]) }} @endif
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-ui.button :href="route('elections.show', $election)" size="sm" class="min-h-11">
                                {{ $manager ? __('Open') : ($election->isOpen() && ! $voted->has($election->id) ? __('Vote now') : __('View')) }}
                            </x-ui.button>
                            @if ($manager && $election->isDraft())
                                <x-ui.button :href="route('elections.edit', $election)" variant="secondary" size="sm" class="min-h-11">{{ __('Set up') }}</x-ui.button>
                            @endif
                        </div>
                    </x-ui.card>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
