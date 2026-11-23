<x-layouts.app :title="$election->title">
    <x-ui.page-header :eyebrow="$election->electorateLabel()" :title="$election->title" :description="$election->description">
        <x-slot:actions>
            <x-ui.badge :tone="$election->statusTone()" dot>{{ $election->statusLabel() }}</x-ui.badge>
            @if ($manager && $election->isDraft())
                <x-ui.button :href="route('elections.edit', $election)" variant="secondary" icon="pencil">{{ __('Set up') }}</x-ui.button>
            @endif
            <x-ui.button :href="route('elections.index')" variant="ghost">{{ __('All elections') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-5">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat :label="__('Votes cast')" :value="$turnout" icon="vote" />
        <x-ui.stat :label="__('Voting schools')" :value="$electorate" icon="school" />
        <x-ui.stat :label="__('Turnout')" :value="$electorate > 0 ? (int) round($turnout / $electorate * 100) : 0" format="percent" icon="chart-column" />
    </div>

    @if ($election->closes_at && $election->isOpen())
        <x-ui.alert tone="info" class="mb-6">{{ __('Voting closes :date.', ['date' => $election->closes_at->timezone(config('app.timezone'))->format('l, j F Y \a\t g:ia')]) }}</x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,20rem)]">
        <div class="space-y-6">
            {{-- Ballot: only a proprietor whose school may still vote. --}}
            @if ($canVote && ! $hasVoted)
                <form method="POST" action="{{ route('elections.vote', $election) }}" onsubmit="return confirm(@js(__('Cast your school\'s vote? You cannot change it afterwards.')))">
                    @csrf
                    <x-ui.card :title="__('Cast your school\'s vote')" :description="__('One vote per school. Choose a candidate, then confirm.')">
                        <fieldset>
                            <legend class="sr-only">{{ __('Candidates') }}</legend>
                            <div class="space-y-3">
                                @foreach ($candidates as $candidate)
                                    <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl p-4 ring-1 ring-stone-200 hover:bg-stone-50 has-[:checked]:bg-brand-50 has-[:checked]:ring-brand-600">
                                        <input type="radio" name="candidate_id" value="{{ $candidate->id }}" required class="mt-1 size-5 border-stone-300 text-brand-700 focus:ring-brand-600">
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-stone-900">{{ $candidate->name }}</span>
                                            @if ($candidate->school)<span class="block text-xs text-stone-500">{{ $candidate->school->name }}</span>@endif
                                            @if ($candidate->statement)<span class="mt-1 block whitespace-pre-wrap text-sm text-stone-700">{{ $candidate->statement }}</span>@endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <x-ui.button type="submit" class="mt-5" icon-right="send">{{ __('Cast vote') }}</x-ui.button>
                    </x-ui.card>
                </form>
            @elseif ($hasVoted)
                <x-ui.alert tone="success" :title="__('Your school has voted')">{{ __('Your vote is recorded and cannot be changed. Nobody can see who your school voted for.') }}</x-ui.alert>
            @elseif ($school && $election->isOpen() && ! $canVote)
                <x-ui.alert tone="warning">{{ __('Only schools in good standing (active membership) can vote in this election.') }}</x-ui.alert>
            @endif

            @if ($results !== null)
                <x-ui.card :title="__('Results')" :description="trans_choice(':count vote counted|:count votes counted', $turnout)">
                    <ul class="space-y-4">
                        @foreach ($results as $candidate)
                            @php $percent = $turnout > 0 ? (int) round($candidate->votes_count / $turnout * 100) : 0; @endphp
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="font-semibold text-stone-900">
                                        {{ $candidate->name }}
                                        @if ($loop->first && $turnout > 0 && $candidate->votes_count > ($results[1]->votes_count ?? -1))
                                            <x-ui.badge tone="success" class="ml-2">{{ __('Winner') }}</x-ui.badge>
                                        @endif
                                    </span>
                                    <span class="tabular text-stone-600">{{ $candidate->votes_count }} · {{ $percent }}%</span>
                                </div>
                                <div class="mt-1 h-2 overflow-hidden rounded-full bg-stone-100" role="img" aria-label="{{ $candidate->name }}: {{ $percent }}%">
                                    <div class="h-full rounded-full bg-brand-600" style="width: {{ $percent }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @elseif (! $canVote || $hasVoted)
                <x-ui.card :title="__('Candidates')" :description="__('Results appear when voting closes.')">
                    <ul class="space-y-3">
                        @foreach ($candidates as $candidate)
                            <li class="rounded-xl bg-stone-50 p-4 ring-1 ring-stone-200">
                                <p class="font-semibold text-stone-900">{{ $candidate->name }}</p>
                                @if ($candidate->school)<p class="text-xs text-stone-500">{{ $candidate->school->name }}</p>@endif
                                @if ($candidate->statement)<p class="mt-1 whitespace-pre-wrap text-sm text-stone-700">{{ $candidate->statement }}</p>@endif
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-4">
            @if ($manager)
                <x-ui.card :title="__('Controls')">
                    <div class="flex flex-wrap gap-3">
                        @if ($election->isDraft())
                            <form method="POST" action="{{ route('elections.open', $election) }}">
                                @csrf
                                <x-ui.button type="submit" icon-right="send">{{ __('Open voting') }}</x-ui.button>
                            </form>
                        @elseif ($election->isOpen())
                            <form method="POST" action="{{ route('elections.close', $election) }}" onsubmit="return confirm(@js(__('Close voting and publish results?')))">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" icon="lock">{{ __('Close voting') }}</x-ui.button>
                            </form>
                        @endif
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('Schools that have voted')" :description="__('Who voted, never who they voted for.')">
                    @if ($voters->isEmpty())
                        <p class="text-sm text-stone-500">{{ __('No votes yet.') }}</p>
                    @else
                        <ul class="divide-y divide-stone-100 text-sm">
                            @foreach ($voters as $vote)
                                <li class="flex items-center justify-between gap-3 py-2">
                                    <span class="min-w-0 truncate text-stone-800">{{ $vote->school->name }}</span>
                                    <span class="shrink-0 text-xs text-stone-500 tabular">{{ $vote->cast_at->timezone(config('app.timezone'))->format('j M, g:ia') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
