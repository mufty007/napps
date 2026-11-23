@php
    $textarea = 'block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 placeholder:text-stone-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm';
    $label = 'block text-sm font-medium text-stone-800';
    $stageOptions = collect($stages)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();

    $items = $events->map(function ($event) {
        $title = $event->type->label();

        if ($event->type === \Modules\Claims\Enums\ClaimEventType::StageChange && ($event->meta['to'] ?? null)) {
            $to = \Modules\Claims\Enums\ClaimStage::tryFrom($event->meta['to'])?->label();
            $from = \Modules\Claims\Enums\ClaimStage::tryFrom((string) ($event->meta['from'] ?? ''))?->label();
            $title = $from ? __(':from → :to', ['from' => $from, 'to' => $to]) : __('Stage: :to', ['to' => $to]);
        }

        return [
            'state' => 'done',
            'title' => $title,
            'meta' => ($event->user?->name ?? __('System')).' · '.$event->created_at->format('j M Y, g:ia'),
            'body' => $event->body,
        ];
    })->all();

    $uploadCollections = collect(\Modules\Claims\Models\ClaimCase::DOCUMENTS)
        ->only($canHandle ? array_keys(\Modules\Claims\Models\ClaimCase::DOCUMENTS) : \Modules\Claims\Models\ClaimCase::SCHOOL_DOCUMENTS)
        ->map(fn ($l) => __($l))->all();
@endphp

<x-layouts.app :title="$case->reference">
    <x-ui.page-header
        :eyebrow="$case->reference"
        :title="$case->debtor_name"
        :description="__(':school · :amount · :days days overdue', ['school' => $case->school?->name, 'amount' => $case->amount(), 'days' => $case->days_overdue])"
    >
        <x-slot:actions>
            <x-ui.badge :tone="$case->stage->tone()" dot>{{ $case->stage->label() }}</x-ui.badge>
            <x-ui.button :href="route('claims.index')" variant="secondary" icon="arrow-left">{{ __('All claims') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        {{-- Left: timeline and claim pack --}}
        <div class="min-w-0 space-y-6">
            @if ($case->summary)
                <x-ui.card :title="__('Summary')">
                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-stone-700">{{ $case->summary }}</p>
                    @if ($case->notes)
                        <div class="mt-4 rounded-xl bg-stone-50 p-4 text-sm text-stone-700 ring-1 ring-stone-200">
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ __('Earlier notes') }}</p>
                            <p class="mt-2 whitespace-pre-wrap">{{ $case->notes }}</p>
                        </div>
                    @endif
                </x-ui.card>
            @endif

            <x-ui.card :title="__('Activity')" :description="__('Everything that has happened on this claim, newest first.')">
                <x-ui.timeline :items="$items" />
            </x-ui.card>

            <x-ui.card :title="__('Claim pack')" :description="__('What the lawyer needs before going to court.')">
                @foreach ($checklist as $row)
                    @php $last = collect($row['files'])->last(); @endphp
                    <div>
                        <x-ui.doc-row :title="$row['title']" :status="$row['status']" :meta="$row['meta']" :href="$last['url'] ?? null" />
                        @if (count($row['files']) > 1)
                            <ul class="mb-2 ml-12 space-y-0.5 border-l border-stone-100 pl-4 text-sm">
                                @foreach ($row['files'] as $file)
                                    <li><a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center text-brand-700 hover:text-brand-800">{{ $file['name'] }} <span class="ml-2 text-xs text-stone-500">{{ $file['at'] }}</span></a></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach

                @if ($canContribute)
                    <div class="mt-5 flex flex-wrap items-start gap-3 border-t border-stone-100 pt-5">
                        @if ($canGenerate)
                            <form method="POST" action="{{ route('claims.demand-letter', $case) }}" x-data="ajaxForm()" @submit.prevent="submit($el)">
                                @csrf
                                <x-ui.button type="submit" variant="accent" icon="file-text" x-bind:disabled="busy">{{ __('Generate demand letter') }}</x-ui.button>
                            </form>
                        @endif
                        <details class="w-full" @if ($errors->has('file') || $errors->has('collection')) open @endif>
                            <summary class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl px-4 text-sm font-semibold text-stone-800 ring-1 ring-inset ring-stone-300 hover:bg-stone-50">
                                <x-ui.icon name="upload" size="size-4" /> {{ __('Add a document') }}
                            </summary>
                            <form method="POST" action="{{ route('claims.documents.store', $case) }}" enctype="multipart/form-data" class="mt-4 space-y-4" x-data="ajaxForm()" @submit.prevent="submit($el)">
                                @csrf
                                <x-ui.select name="collection" :label="__('Type')" :options="$uploadCollections" :value="old('collection', 'evidence')" />
                                <x-ui.input name="title" :label="__('Title (optional)')" />
                                <div class="space-y-1.5">
                                    <label for="file" class="{{ $label }}">{{ __('File') }}</label>
                                    <input id="file" type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                        class="block w-full min-h-11 text-sm text-stone-700 file:mr-3 file:min-h-11 file:rounded-xl file:border-0 file:bg-brand-50 file:px-4 file:font-semibold file:text-brand-800 hover:file:bg-brand-100">
                                    <p class="text-sm text-stone-500">{{ __('PDF, photo or Word file, up to 10 MB.') }}</p>
                                    @error('file')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                                </div>
                                <x-ui.button type="submit" icon="upload">{{ __('Upload') }}</x-ui.button>
                            </form>
                        </details>
                    </div>
                @endif
            </x-ui.card>
        </div>

        {{-- Right: properties and actions --}}
        <div class="min-w-0 space-y-6">
            <x-ui.side-panel :title="__('Case properties')">
                <dl class="space-y-4 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-stone-500">{{ __('Stage') }}</dt>
                        <dd><x-ui.badge :tone="$case->stage->tone()">{{ $case->stage->label() }}</x-ui.badge></dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-stone-500">{{ __('Lawyer') }}</dt>
                        <dd class="text-right font-semibold text-stone-900">{{ $case->lawyer?->name ?? __('Unassigned') }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-stone-500">{{ __('Court') }}</dt>
                        <dd class="text-right text-stone-900">
                            {{ $case->court_name ?? '—' }}
                            @if ($case->court_file_number)<span class="block text-xs text-stone-500 tabular">{{ $case->court_file_number }}</span>@endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-stone-500">{{ __('Next hearing') }}</dt>
                        <dd class="text-right text-stone-900 tabular">
                            @if ($nextHearing)
                                {{ $nextHearing->scheduled_at->format('j M Y, g:ia') }}
                                @if ($nextHearing->venue)<span class="block text-xs text-stone-500">{{ $nextHearing->venue }}</span>@endif
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 border-t border-stone-100 pt-4">
                        <dt class="text-stone-500">{{ __('Amount claimed') }}</dt>
                        <dd class="font-semibold tabular text-stone-900">{{ $case->amount() }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-stone-500">{{ __('Settled') }}</dt>
                        <dd class="tabular text-stone-900">{{ \App\Support\Money::format($totals['settled_kobo']) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-stone-500">{{ __('Paid so far') }}</dt>
                        <dd class="tabular text-stone-900">{{ \App\Support\Money::format($totals['paid_kobo']) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-stone-500">{{ __('Still owed') }}</dt>
                        <dd class="font-semibold tabular text-stone-900">{{ \App\Support\Money::format($totals['outstanding_kobo']) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-stone-500">{{ __('Legal fees') }}</dt>
                        <dd class="tabular text-stone-900">{{ \App\Support\Money::format($totals['fees_kobo']) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 border-t border-stone-100 pt-4">
                        <dt class="text-stone-500">{{ __('School') }}</dt>
                        <dd class="text-right text-stone-900">{{ $case->school?->name }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-stone-500">{{ __('Opened by') }}</dt>
                        <dd class="text-right text-stone-900">{{ $case->opener?->name }}</dd>
                    </div>
                    @if ($case->debtor_phone)
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-stone-500">{{ __('Debtor phone') }}</dt>
                            <dd class="text-right tabular text-stone-900">{{ \App\Support\PhoneNumber::format($case->debtor_phone) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-stone-500">{{ __('Escalated') }}</dt>
                        <dd class="text-right text-stone-900">{{ $case->escalated_at?->format('j M Y') }}</dd>
                    </div>
                </dl>

                @if ($canAssign)
                    <form method="POST" action="{{ route('claims.assign', $case) }}" class="space-y-3 border-t border-stone-100 pt-5" x-data="ajaxForm()" @submit.prevent="submit($el)">
                        @csrf
                        <x-ui.select name="lawyer_id" :label="__('Assign a panel lawyer')" :options="$lawyers" :value="$case->lawyer_id" :placeholder="__('Choose a lawyer')" required />
                        <x-ui.button type="submit" variant="secondary" icon="user-plus">{{ __('Assign') }}</x-ui.button>
                    </form>
                @endif
            </x-ui.side-panel>

            @if ($case->hearings->isNotEmpty())
                <x-ui.card :title="__('Hearings')">
                    <ul class="divide-y divide-stone-100">
                        @foreach ($case->hearings->sortByDesc('scheduled_at') as $hearing)
                            <li class="py-3 first:pt-0 last:pb-0">
                                <p class="font-semibold tabular text-stone-900">{{ $hearing->scheduled_at->format('D j M Y, g:ia') }}</p>
                                @if ($hearing->venue)<p class="text-sm text-stone-500">{{ $hearing->venue }}</p>@endif
                                @if ($hearing->outcome)
                                    <p class="mt-1 whitespace-pre-wrap text-sm text-stone-700">{{ $hearing->outcome }}</p>
                                @endif
                                @if ($canHandle)
                                    <details class="mt-1">
                                        <summary class="inline-flex min-h-11 cursor-pointer items-center text-sm font-semibold text-brand-700 hover:text-brand-800">{{ $hearing->outcome ? __('Edit outcome') : __('Record outcome') }}</summary>
                                        <form method="POST" action="{{ route('claims.hearings.update', [$case, $hearing]) }}" class="mt-2 space-y-3" x-data="ajaxForm()" @submit.prevent="submit($el)">
                                            @csrf
                                            @method('PUT')
                                            <textarea name="outcome" rows="3" maxlength="3000" class="{{ $textarea }}" placeholder="{{ __('Adjourned to…, judgment reserved…') }}" aria-label="{{ __('Hearing outcome') }}">{{ $hearing->outcome }}</textarea>
                                            <x-ui.button type="submit" size="sm" variant="secondary">{{ __('Save outcome') }}</x-ui.button>
                                        </form>
                                    </details>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @if ($case->settlements->isNotEmpty() || $case->fees->isNotEmpty())
                <x-ui.card :title="__('Money')">
                    <ul class="divide-y divide-stone-100 text-sm">
                        @foreach ($case->settlements as $settlement)
                            <li class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0">
                                    <p class="font-semibold text-stone-900">{{ __('Settlement') }}</p>
                                    <p class="text-stone-500">{{ $settlement->paid_at ? __('Paid :date', ['date' => $settlement->paid_at->format('j M Y')]) : __('Agreed, not yet paid') }}</p>
                                    @if ($settlement->terms)<p class="mt-1 whitespace-pre-wrap text-stone-600">{{ $settlement->terms }}</p>@endif
                                </div>
                                <span class="shrink-0 font-semibold tabular text-stone-900">{{ $settlement->amount() }}</span>
                            </li>
                        @endforeach
                        @foreach ($case->fees as $fee)
                            <li class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0">
                                    <p class="font-semibold text-stone-900">{{ $fee->description }}</p>
                                    <p class="text-stone-500">{{ __('Fee · :date', ['date' => $fee->created_at->format('j M Y')]) }}</p>
                                </div>
                                <span class="shrink-0 tabular text-stone-900">{{ $fee->amount() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @if ($canHandle)
                <x-ui.card :title="__('Work the case')">
                  <div x-data="{ tab: @js(old('_tab', 'stage')) }">
                    <div role="tablist" class="-mx-1 mb-5 flex flex-wrap gap-1" aria-label="{{ __('Case actions') }}">
                        @foreach (['stage' => __('Stage'), 'note' => __('Note'), 'hearing' => __('Hearing'), 'settlement' => __('Settlement'), 'fee' => __('Fee')] as $key => $name)
                            <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                                :class="tab === '{{ $key }}' ? 'bg-brand-700 text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200'"
                                class="inline-flex min-h-11 items-center rounded-full px-4 text-sm font-semibold transition-colors">{{ $name }}</button>
                        @endforeach
                    </div>

                    <form x-show="tab === 'stage'" method="POST" action="{{ route('claims.update', $case) }}" class="space-y-4" x-data="ajaxForm()" @submit.prevent="submit($el)">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_tab" value="stage">
                        <x-ui.select name="stage" :label="__('Stage')" :value="$case->stage->value" :options="$stageOptions" required />
                        <div class="space-y-1.5">
                            <label for="stage_note" class="{{ $label }}">{{ __('Note on this move (optional)') }}</label>
                            <textarea id="stage_note" name="stage_note" rows="2" maxlength="2000" class="{{ $textarea }}">{{ old('stage_note') }}</textarea>
                        </div>
                        <x-ui.input name="court_name" :label="__('Court name')" :value="$case->court_name" />
                        <x-ui.input name="court_file_number" :label="__('File number')" :value="$case->court_file_number" />
                        <x-ui.button type="submit" icon-right="check">{{ __('Save') }}</x-ui.button>
                    </form>

                    <form x-show="tab === 'note'" x-cloak method="POST" action="{{ route('claims.notes.store', $case) }}" class="space-y-4" x-data="ajaxForm()" @submit.prevent="submit($el)">
                        @csrf
                        <input type="hidden" name="_tab" value="note">
                        <div class="space-y-1.5">
                            <label for="note_body" class="{{ $label }}">{{ __('Internal note') }}</label>
                            <textarea id="note_body" name="body" rows="4" maxlength="5000" required class="{{ $textarea }}" placeholder="{{ __('Only the legal team and the association see notes.') }}">{{ old('body') }}</textarea>
                            @error('body')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <x-ui.button type="submit" icon-right="check">{{ __('Add note') }}</x-ui.button>
                    </form>

                    <form x-show="tab === 'hearing'" x-cloak method="POST" action="{{ route('claims.hearings.store', $case) }}" class="space-y-4" x-data="ajaxForm()" @submit.prevent="submit($el)">
                        @csrf
                        <input type="hidden" name="_tab" value="hearing">
                        <x-ui.input name="scheduled_at" type="datetime-local" :label="__('Date and time')" required />
                        <x-ui.input name="venue" :label="__('Venue')" :value="$case->court_name" />
                        <x-ui.button type="submit" icon="calendar">{{ __('Schedule hearing') }}</x-ui.button>
                    </form>

                    <form x-show="tab === 'settlement'" x-cloak method="POST" action="{{ route('claims.settlements.store', $case) }}" class="space-y-4" x-data="ajaxForm()" @submit.prevent="submit($el)">
                        @csrf
                        <input type="hidden" name="_tab" value="settlement">
                        <x-ui.input name="amount" inputmode="decimal" prefix="₦" :label="__('Amount settled')" required />
                        <x-ui.input name="paid_at" type="date" :label="__('Date paid (leave empty if not yet paid)')" :max="now()->toDateString()" />
                        <div class="space-y-1.5">
                            <label for="terms" class="{{ $label }}">{{ __('Terms') }}</label>
                            <textarea id="terms" name="terms" rows="3" maxlength="5000" class="{{ $textarea }}">{{ old('terms') }}</textarea>
                        </div>
                        <x-ui.button type="submit" icon-right="check">{{ __('Record settlement') }}</x-ui.button>
                    </form>

                    <form x-show="tab === 'fee'" x-cloak method="POST" action="{{ route('claims.fees.store', $case) }}" class="space-y-4" x-data="ajaxForm()" @submit.prevent="submit($el)">
                        @csrf
                        <input type="hidden" name="_tab" value="fee">
                        <x-ui.input name="description" :label="__('What was the fee for?')" :placeholder="__('Filing fee, process server…')" required />
                        <x-ui.input name="amount" inputmode="decimal" prefix="₦" :label="__('Amount')" required />
                        <x-ui.button type="submit" icon-right="check">{{ __('Record fee') }}</x-ui.button>
                    </form>
                  </div>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
