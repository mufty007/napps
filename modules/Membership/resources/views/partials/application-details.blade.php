{{-- Needs: $application, $documentRoute (route name taking [application, collection]) --}}
@php $school = $application->school; @endphp

<x-ui.card :title="__('School details')">
    <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
        <div class="sm:col-span-2">
            <dt class="text-stone-500">{{ __('School name') }}</dt>
            <dd class="mt-0.5 font-semibold text-stone-900">{{ $school->name }}</dd>
        </div>
        <div>
            <dt class="text-stone-500">{{ __('CAC / registration number') }}</dt>
            <dd class="mt-0.5 font-semibold text-stone-900">{{ $school->registration_number }}</dd>
        </div>
        <div>
            <dt class="text-stone-500">{{ __('Ministry approval number') }}</dt>
            <dd class="mt-0.5 font-semibold text-stone-900">
                @if ($school->ministry_approval_number)
                    {{ $school->ministry_approval_number }}
                @else
                    <x-ui.badge tone="warning">{{ __('Not provided') }}</x-ui.badge>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-stone-500">{{ __('Levels') }}</dt>
            <dd class="mt-0.5 font-semibold capitalize text-stone-900">{{ collect($school->levels)->implode(', ') ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-stone-500">{{ __('Pupils') }}</dt>
            <dd class="mt-0.5 font-semibold text-stone-900 tabular">{{ number_format((int) $school->pupil_count) }}</dd>
        </div>
        <div>
            <dt class="text-stone-500">{{ __('Chapter') }}</dt>
            <dd class="mt-0.5 font-semibold text-stone-900">{{ $school->chapter?->name }}, {{ $school->state?->name }}</dd>
        </div>
        <div>
            <dt class="text-stone-500">{{ __('Local government area') }}</dt>
            <dd class="mt-0.5 font-semibold text-stone-900">{{ $school->lga ?: '—' }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-stone-500">{{ __('Address') }}</dt>
            <dd class="mt-0.5 font-semibold text-stone-900">
                {{ $school->address }}
                @if ($school->latitude && $school->longitude)
                    <a href="https://www.google.com/maps?q={{ $school->latitude }},{{ $school->longitude }}" target="_blank" rel="noopener" class="mt-1 flex min-h-11 items-center gap-1.5 font-semibold text-brand-700 hover:text-brand-800">
                        <x-ui.icon name="map-pin" size="size-4" /> {{ __('Open GPS location in Maps') }}
                    </a>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-stone-500">{{ __('School phone') }}</dt>
            <dd class="mt-0.5 font-semibold text-stone-900">{{ \App\Support\PhoneNumber::format($school->phone) }}</dd>
        </div>
        <div>
            <dt class="text-stone-500">{{ __('School email') }}</dt>
            <dd class="mt-0.5 font-semibold text-stone-900 break-all">{{ $school->email ?: '—' }}</dd>
        </div>
    </dl>
</x-ui.card>

<x-ui.card :title="__('Documents')" :padding="false">
    <ul class="divide-y divide-stone-100">
        @foreach (\Modules\Membership\Models\MembershipApplication::DOCUMENTS as $collection => [$label, $required])
            @php $media = $application->getFirstMedia($collection); @endphp
            <li class="flex min-h-14 flex-wrap items-center justify-between gap-3 px-5 py-3">
                <span class="flex min-w-0 items-center gap-3 text-sm">
                    <x-ui.icon :name="$media ? 'file-check' : 'file-x'" :class="$media ? 'text-brand-700' : 'text-stone-400'" />
                    <span class="min-w-0">
                        <span class="block font-medium text-stone-900">{{ __($label) }}</span>
                        @if ($media)
                            <span class="block truncate text-stone-500">{{ $media->file_name }} · {{ $media->human_readable_size }}</span>
                        @endif
                    </span>
                </span>
                @if ($media)
                    <x-ui.button :href="route($documentRoute, [$application, $collection])" variant="secondary" size="sm" icon="eye" target="_blank" rel="noopener">{{ __('Open') }}</x-ui.button>
                @else
                    <span class="text-sm text-stone-500">{{ $required ? __('Missing') : __('Not provided') }}</span>
                @endif
            </li>
        @endforeach
    </ul>
</x-ui.card>
