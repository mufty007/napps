@php
    $editing = $election->exists;
    $draft = ! $editing || $election->isDraft();
@endphp
<x-layouts.app :title="$editing ? __('Set up election') : __('New election')">
    <x-ui.page-header
        :eyebrow="__('Elections')"
        :title="$editing ? $election->title : __('New election')"
        :description="$editing ? null : __('Choose who votes. You add the candidates next.')"
    >
        @if ($editing)
            <x-slot:actions>
                <x-ui.badge :tone="$election->statusTone()" dot>{{ $election->statusLabel() }}</x-ui.badge>
                <x-ui.button :href="route('elections.show', $election)" variant="secondary">{{ __('View') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($errors->has('election'))
        <x-ui.alert tone="danger" class="mb-5">{{ $errors->first('election') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <form method="POST" action="{{ $editing ? route('elections.update', $election) : route('elections.store') }}" class="space-y-5">
            @csrf
            @if ($editing) @method('PUT') @endif

            <x-ui.card :title="__('Details')">
                <div class="space-y-5">
                    <x-ui.input name="title" :label="__('Position or title')" :value="$election->title" :placeholder="__('e.g. Chapter chairman 2026')" required maxlength="180" />
                    <div class="space-y-1.5">
                        <label for="description" class="block text-sm font-medium text-stone-800">{{ __('About this election (optional)') }}</label>
                        <textarea id="description" name="description" rows="4" maxlength="5000" class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('description', $election->description) }}</textarea>
                    </div>
                    @if ($draft && ! $editing)
                        <x-ui.select name="area" :label="__('Who votes')" :value="$areaKey" :options="$areas" :hint="__('Active member schools in this chapter or state, one vote each.')" required />
                    @elseif ($editing)
                        <div>
                            <p class="text-sm font-medium text-stone-800">{{ __('Who votes') }}</p>
                            <p class="mt-1 text-sm text-stone-600">{{ $election->electorateLabel() }}</p>
                        </div>
                    @endif
                    <x-ui.input name="closes_at" type="datetime-local" :label="__('Voting closes (optional)')" :value="$election->closes_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i')" />
                </div>
            </x-ui.card>

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit">{{ $editing ? __('Save changes') : __('Save and add candidates') }}</x-ui.button>
                <x-ui.button :href="route('elections.index')" variant="ghost">{{ __('Back') }}</x-ui.button>
            </div>
        </form>

        @if ($editing)
            <div class="space-y-6">
                <x-ui.card id="candidates" :title="__('Candidates')" :description="trans_choice(':count candidate|:count candidates', $candidates->count())">
                    @if ($candidates->isEmpty())
                        <x-ui.empty-state icon="users-round" :title="__('No candidates yet')" :description="__('Add at least two before opening voting.')" />
                    @else
                        <ul class="space-y-3">
                            @foreach ($candidates as $candidate)
                                <li class="flex items-start justify-between gap-3 rounded-xl bg-stone-50 p-4 ring-1 ring-stone-200">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-stone-900">{{ $candidate->name }}</p>
                                        @if ($candidate->school)<p class="text-xs text-stone-500">{{ $candidate->school->name }}</p>@endif
                                        @if ($candidate->statement)<p class="mt-2 whitespace-pre-wrap text-sm text-stone-700">{{ $candidate->statement }}</p>@endif
                                    </div>
                                    @if ($draft)
                                        <form method="POST" action="{{ route('elections.candidates.destroy', [$election, $candidate]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="flex size-11 items-center justify-center rounded-xl text-red-600 hover:bg-red-50">
                                                <x-ui.icon name="trash-2" /><span class="sr-only">{{ __('Remove candidate') }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>

                @if ($draft)
                    <x-ui.card :title="__('Add a candidate')">
                        <form method="POST" action="{{ route('elections.candidates.store', $election) }}" class="space-y-5">
                            @csrf
                            <x-ui.input name="name" :label="__('Name')" maxlength="120" required />
                            <x-ui.select name="school_id" :label="__('School (optional)')" :options="$schools->pluck('name', 'id')->all()" :placeholder="__('No school')" />
                            <div class="space-y-1.5">
                                <label for="statement" class="block text-sm font-medium text-stone-800">{{ __('Statement (optional)') }}</label>
                                <textarea id="statement" name="statement" rows="3" maxlength="2000" class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('statement') }}</textarea>
                            </div>
                            <x-ui.button type="submit" icon="plus">{{ __('Add candidate') }}</x-ui.button>
                        </form>
                    </x-ui.card>

                    <x-ui.card :title="__('Open voting')">
                        <div class="flex flex-wrap gap-3">
                            <form method="POST" action="{{ route('elections.open', $election) }}">
                                @csrf
                                <x-ui.button type="submit" icon-right="send">{{ __('Open voting') }}</x-ui.button>
                            </form>
                            <form method="POST" action="{{ route('elections.destroy', $election) }}" onsubmit="return confirm(@js(__('Delete this election?')))">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" variant="ghost" icon="trash-2" class="text-red-700">{{ __('Delete') }}</x-ui.button>
                            </form>
                        </div>
                        <p class="mt-3 text-sm text-stone-500">{{ __('Once voting opens, candidates are locked.') }}</p>
                    </x-ui.card>
                @endif
            </div>
        @endif
    </div>
</x-layouts.app>
