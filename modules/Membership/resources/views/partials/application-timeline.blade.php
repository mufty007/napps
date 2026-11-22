{{-- Progress through apply -> chapter verifies -> state approves. Needs: $application --}}
@php
    $status = $application->status;
    $rejectedAt = $application->rejected_stage;
    $steps = [
        [
            'title' => __('Application sent'),
            'detail' => $application->submitted_at?->format('j M Y, g:ia'),
            'state' => 'done',
        ],
        [
            'title' => __('Chapter verification'),
            'detail' => match (true) {
                $application->verified_at !== null => __('Verified :date by :name', ['date' => $application->verified_at->format('j M Y'), 'name' => $application->verifier?->name]),
                $rejectedAt === 'chapter' => __('Not approved :date', ['date' => $application->rejected_at?->format('j M Y')]),
                default => __(':chapter is reviewing it', ['chapter' => $application->school->chapter?->name]),
            },
            'state' => match (true) {
                $application->verified_at !== null => 'done',
                $rejectedAt === 'chapter' => 'failed',
                default => 'current',
            },
        ],
        [
            'title' => __('State approval'),
            'detail' => match (true) {
                $application->approved_at !== null => __('Approved :date', ['date' => $application->approved_at->format('j M Y')]),
                $rejectedAt === 'state' => __('Not approved :date', ['date' => $application->rejected_at?->format('j M Y')]),
                $status === \Modules\Membership\Enums\ApplicationStatus::Verified => __(':state State is reviewing it', ['state' => $application->school->state?->name]),
                default => __('After chapter verification'),
            },
            'state' => match (true) {
                $application->approved_at !== null => 'done',
                $rejectedAt === 'state' => 'failed',
                $status === \Modules\Membership\Enums\ApplicationStatus::Verified => 'current',
                default => 'upcoming',
            },
        ],
        [
            'title' => __('Certificate issued'),
            'detail' => $status === \Modules\Membership\Enums\ApplicationStatus::Approved ? __('Membership number :number', ['number' => $application->school->membership_number]) : __('With a QR code anyone can check'),
            'state' => $status === \Modules\Membership\Enums\ApplicationStatus::Approved ? 'done' : 'upcoming',
        ],
    ];
@endphp

<ol class="space-y-0">
    @foreach ($steps as $i => $step)
        <li class="relative flex gap-4 pb-6 last:pb-0">
            @unless ($loop->last)
                <span aria-hidden="true" @class(['absolute left-[15px] top-8 -bottom-0 w-0.5', 'bg-brand-600' => $step['state'] === 'done', 'bg-stone-200' => $step['state'] !== 'done'])></span>
            @endunless
            <span @class([
                'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white',
                'bg-brand-600 text-white' => $step['state'] === 'done',
                'bg-accent-400 text-stone-950' => $step['state'] === 'current',
                'bg-red-600 text-white' => $step['state'] === 'failed',
                'bg-stone-100 text-stone-400' => $step['state'] === 'upcoming',
            ])>
                @switch($step['state'])
                    @case('done') <x-ui.icon name="check" size="size-4" /> @break
                    @case('failed') <x-ui.icon name="x" size="size-4" /> @break
                    @default <span class="text-xs font-bold">{{ $i + 1 }}</span>
                @endswitch
            </span>
            <div class="min-w-0 pt-1">
                <p class="text-sm font-semibold text-stone-900">
                    {{ $step['title'] }}
                    <span class="sr-only">({{ match ($step['state']) { 'done' => __('done'), 'failed' => __('not approved'), 'current' => __('in progress'), default => __('not started') } }})</span>
                </p>
                <p class="text-sm text-stone-500">{{ $step['detail'] }}</p>
            </div>
        </li>
    @endforeach
</ol>
