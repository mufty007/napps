<div data-ajax-results>
    @php
        $link = fn (array $params) => route('records.students.index', array_filter($params, fn ($v) => $v !== null && $v !== ''));
        $tabs = [];
        foreach (\Modules\Records\Enums\StudentStatus::cases() as $s) {
            $tabs[$s->value] = [
                'label' => $s->label(),
                'count' => (int) ($counts[$s->value] ?? 0),
                'href' => $link(['status' => $s->value, 'q' => $search, 'class' => $class]),
                'ajax' => true,
            ];
        }
    @endphp

    <x-ui.status-tabs class="mb-4" :active="$status->value" :tabs="$tabs" />

    @if ($students->isEmpty())
        <x-ui.card>
            @if ($search !== '' || $class !== '')
                <x-ui.empty-state icon="search" :title="__('No pupils match')" :description="__('Try a different search or clear the class filter.')">
                    <x-ui.button :href="route('records.students.index', ['status' => $status->value])" variant="secondary" data-ajax-tab>{{ __('Clear filters') }}</x-ui.button>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state
                    icon="graduation-cap"
                    :title="__('No pupils here yet')"
                    :description="$status === \Modules\Records\Enums\StudentStatus::Active ? __('Enrol your first pupil to start the register.') : __('Pupils who change status appear here.')"
                >
                    @if ($canEnrol && $status === \Modules\Records\Enums\StudentStatus::Active)
                        <x-ui.button :href="route('records.students.create')" icon="plus">{{ __('Enrol a pupil') }}</x-ui.button>
                    @endif
                </x-ui.empty-state>
            @endif
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count pupil|:count pupils', $students->total()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th scope="col">{{ __('Pupil') }}</th>
                <th scope="col" class="hidden sm:table-cell">{{ __('Class') }}</th>
                <th scope="col" class="hidden md:table-cell">{{ __('Guardian') }}</th>
                @if ($showSchool)
                    <th scope="col" class="hidden lg:table-cell">{{ __('School') }}</th>
                @endif
                <th scope="col" class="hidden md:table-cell">{{ __('Debtor check') }}</th>
                <th scope="col">{{ __('Status') }}</th>
            </x-slot:head>
            @foreach ($students as $student)
                <tr>
                    <td class="max-w-[14rem]">
                        <a href="{{ route('records.students.show', $student) }}" class="inline-flex min-h-11 flex-col justify-center font-semibold text-brand-700 hover:text-brand-800">
                            <span class="truncate">{{ $student->last_name }}, {{ $student->first_name }}</span>
                            @if ($student->admission_no)
                                <span class="text-xs font-normal tabular text-stone-500">{{ $student->admission_no }}</span>
                            @endif
                        </a>
                        <span class="block truncate text-xs text-stone-500 sm:hidden">{{ $student->class_name }}</span>
                    </td>
                    <td class="hidden whitespace-nowrap sm:table-cell">{{ $student->class_name ?? '—' }}</td>
                    <td class="hidden max-w-[14rem] md:table-cell">
                        <span class="block truncate">{{ $student->guardian_name }}</span>
                        @if ($student->guardian_phone)
                            <span class="block text-xs tabular text-stone-500">{{ \App\Support\PhoneNumber::format($student->guardian_phone) }}</span>
                        @endif
                    </td>
                    @if ($showSchool)
                        <td class="hidden max-w-[14rem] truncate lg:table-cell">{{ $student->school?->name }}</td>
                    @endif
                    <td class="hidden md:table-cell">
                        <x-ui.badge :tone="$student->debtor_check_consent ? 'brand' : 'neutral'">{{ $student->debtor_check_consent ? __('Consented') : __('Not consented') }}</x-ui.badge>
                    </td>
                    <td><x-ui.badge :tone="$student->status->tone()">{{ $student->status->label() }}</x-ui.badge></td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $students->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</div>
