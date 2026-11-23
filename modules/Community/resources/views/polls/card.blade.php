{{-- Needs: $poll with post.space and options loaded. --}}
@php
    $state = app(\Modules\Community\Services\PollService::class)->present($poll, auth()->user(), app(\Modules\Community\Services\SpaceDirectory::class));
    $tally = $state['tally'];
    $denominator = max(1, $poll->multiple ? $state['participants'] : array_sum($tally));
    $editable = $state['canVote'] && ! $poll->formal && $state['mine'] !== [];
@endphp
<div id="poll-{{ $poll->id }}" data-poll class="mt-4 rounded-2xl bg-stone-50 p-4 ring-1 ring-stone-200 sm:p-5"
    x-data="pollCard({ editing: @js($state['canVote'] && ! $state['showResults']) })">
    <div class="flex flex-wrap items-center justify-between gap-2">
        @if ($poll->formal)
            <x-ui.badge tone="brand"><x-ui.icon name="landmark" size="size-3" /> {{ __('Formal vote · one ballot per school') }}</x-ui.badge>
        @else
            <x-ui.badge tone="info"><x-ui.icon name="chart-no-axes-column" size="size-3" /> {{ $poll->multiple ? __('Poll · pick any') : __('Poll') }}</x-ui.badge>
        @endif
        <p class="text-xs font-medium text-stone-500">
            @if ($state['closed'])
                <span class="inline-flex items-center gap-1 text-stone-700"><x-ui.icon name="lock" size="size-3" /> {{ __('Closed :when', ['when' => $poll->endedAt()?->diffForHumans()]) }}</span>
            @elseif ($poll->closes_at)
                {{ __('Closes :when', ['when' => $poll->closes_at->diffForHumans()]) }}
            @else
                {{ __('Open') }}
            @endif
        </p>
    </div>

    {{-- Ballot --}}
    @if ($state['canVote'])
        <form method="POST" action="{{ route('community.polls.vote', $poll) }}" class="mt-3 space-y-2" x-show="editing" @unless ($state['canVote'] && ! $state['showResults']) x-cloak @endunless x-on:submit.prevent="submit($el)">
            @csrf
            <fieldset class="space-y-2">
                <legend class="sr-only">{{ $poll->multiple ? __('Choose any options') : __('Choose one option') }}</legend>
                @foreach ($poll->options as $option)
                    <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl bg-white px-3.5 py-2 ring-1 ring-stone-200 transition-colors hover:ring-brand-300 has-[:checked]:ring-2 has-[:checked]:ring-brand-600">
                        <input type="{{ $poll->multiple ? 'checkbox' : 'radio' }}" name="options[]" value="{{ $option->id }}" @checked(in_array($option->id, $state['mine'], true))
                            @class(['size-5 border-stone-300 text-brand-700 focus:ring-brand-600', 'rounded' => $poll->multiple])>
                        <span class="text-sm font-medium text-stone-800">{{ $option->label }}</span>
                    </label>
                @endforeach
            </fieldset>

            @if ($poll->formal && $state['schools']->count() > 1)
                <x-ui.select name="school_id" :label="__('Voting for')" :options="$state['schools']->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()" />
            @elseif ($poll->formal && $state['schools']->count() === 1)
                <input type="hidden" name="school_id" value="{{ $state['schools']->first()->id }}">
                <p class="text-xs text-stone-500">{{ __('Voting for :school. Your ballot is final.', ['school' => $state['schools']->first()->name]) }}</p>
            @endif

            <p x-show="error" x-cloak x-text="error" class="text-sm font-medium text-red-700" role="alert"></p>
            @error('poll')<p class="text-sm font-medium text-red-700" role="alert">{{ $message }}</p>@enderror

            <div class="flex flex-wrap items-center gap-2 pt-1">
                <x-ui.button type="submit" size="sm" :icon="$poll->formal ? 'vote' : 'check'" x-bind:disabled="busy">{{ $poll->formal ? __('Cast ballot') : __('Vote') }}</x-ui.button>
                @if ($editable)
                    <x-ui.button type="button" size="sm" variant="ghost" x-on:click="editing = false">{{ __('Cancel') }}</x-ui.button>
                @endif
            </div>
        </form>
    @endif

    {{-- Results --}}
    @if ($state['showResults'])
        <ul class="mt-3 space-y-2" @if ($state['canVote']) x-show="! editing" @endif>
            @foreach ($poll->options as $option)
                @php
                    $count = $tally[$option->id] ?? 0;
                    $percent = (int) round($count / $denominator * 100);
                    $chosen = in_array($option->id, $state['mine'], true);
                    $leading = $state['closed'] && $count > 0 && $count === max($tally ?: [0]);
                @endphp
                <li class="relative overflow-hidden rounded-xl bg-white ring-1 ring-stone-200">
                    <span @class(['absolute inset-y-0 left-0', 'bg-brand-100' => $leading || $chosen, 'bg-stone-100' => ! $leading && ! $chosen]) style="width: {{ $percent }}%" aria-hidden="true"></span>
                    <span class="relative flex min-h-12 items-center justify-between gap-3 px-3.5 py-2 text-sm">
                        <span @class(['flex min-w-0 items-center gap-2', 'font-semibold text-stone-900' => $leading, 'font-medium text-stone-800' => ! $leading])>
                            {{ $option->label }}
                            @if ($chosen)<x-ui.icon name="circle-check" size="size-4" class="shrink-0 text-brand-700" /><span class="sr-only">{{ __('(your choice)') }}</span>@endif
                        </span>
                        <span class="shrink-0 font-semibold tabular text-stone-900">{{ $percent }}%<span class="ml-1.5 font-normal text-stone-500">{{ $count }}</span></span>
                    </span>
                </li>
            @endforeach
        </ul>
    @elseif ($poll->formal)
        <p @class(['mt-3 flex items-center gap-2 text-sm text-stone-600', 'rounded-xl bg-white px-3.5 py-3 ring-1 ring-stone-200' => ! $state['canVote']])>
            <x-ui.icon name="eye-off" size="size-4" class="shrink-0 text-stone-400" /> {{ __('Results are hidden until voting closes.') }}
        </p>
    @endif

    @if ($state['notice'])
        <p class="mt-3 text-sm font-medium text-stone-700">{{ $state['notice'] }}</p>
    @endif

    <div class="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-xs text-stone-500">
        <p class="flex flex-wrap items-center gap-x-2">
            @if ($poll->formal)
                <span class="font-semibold text-stone-700">{{ __('Turnout: :voted of :total member schools', ['voted' => $state['participants'], 'total' => $state['electorate']]) }}</span>
            @else
                <span class="font-semibold text-stone-700">{{ trans_choice(':count vote|:count votes', $state['participants']) }}</span>
            @endif
            @if ($poll->anonymous)<span>· {{ __('Anonymous') }}</span>@endif
        </p>
        <div class="flex flex-wrap items-center gap-1">
            @if ($editable)
                <button type="button" x-show="! editing" x-on:click="editing = true" class="inline-flex min-h-11 items-center rounded-lg px-2 font-semibold text-brand-700 hover:bg-white">{{ __('Change vote') }}</button>
            @endif
            @if ($state['canExport'])
                <a href="{{ route('community.polls.export', $poll) }}" class="inline-flex min-h-11 items-center gap-1 rounded-lg px-2 font-semibold text-brand-700 hover:bg-white"><x-ui.icon name="download" size="size-3.5" /> {{ __('Export results') }}</a>
            @endif
            @if ($state['canClose'])
                <form method="POST" action="{{ route('community.polls.close', $poll) }}" x-data="{ sure: false }" x-on:submit.prevent="sure ? submit($el) : (sure = true)">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center rounded-lg px-2 font-semibold text-stone-600 hover:bg-white hover:text-stone-900" x-text="sure ? @js(__('Close now? Results lock')) : @js(__('Close voting'))">{{ __('Close voting') }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
