<x-layouts.app :title="__('Reports')">
    <x-ui.page-header :eyebrow="__('Community')" :title="__('Reports')" :description="__('Reports from members in your area. Hide what breaks the guidelines; every decision is logged.')" />


    <div class="mb-4 flex gap-1">
        @foreach (['open' => __('Open'), 'actioned' => __('Hidden'), 'dismissed' => __('Dismissed')] as $value => $label)
            <a href="{{ route('community.moderation.index', ['status' => $value === 'open' ? null : $value]) }}" @class(['flex min-h-11 items-center rounded-full px-4 text-sm font-semibold ring-1 ring-inset', 'bg-brand-700 text-white ring-brand-700' => $status === $value, 'bg-white text-stone-700 ring-stone-200' => $status !== $value])>{{ $label }}</a>
        @endforeach
    </div>

    <x-ui.card :padding="$reports->isEmpty()">
        @if ($reports->isEmpty())
            <x-ui.empty-state icon="shield-check" :title="__('Nothing to review')" :description="__('Reports from your chapter or state show up here.')" />
        @else
            <ul class="divide-y divide-stone-100">
                @foreach ($reports as $report)
                    @php $item = $report->reportable; @endphp
                    <li class="px-5 py-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <x-ui.badge tone="danger">{{ $report->reasonLabel() }}</x-ui.badge>
                            <span class="text-stone-500">{{ class_basename($report->reportable_type) }} · {{ $report->space?->name ?? __('Private chat') }} · {{ __('reported by :name :when', ['name' => $report->reporter?->name, 'when' => $report->created_at->diffForHumans()]) }}</span>
                        </div>
                        <blockquote class="mt-2 rounded-xl bg-stone-50 px-4 py-3 text-sm text-stone-800 ring-1 ring-stone-200">
                            @if ($item)
                                <p class="text-xs font-semibold text-stone-500">{{ ($item->author ?? $item->sender ?? null)?->name }}</p>
                                <p class="mt-1 line-clamp-4 break-words">{{ $item->title ?? '' }} {{ $item->body ?? '' }}</p>
                            @else
                                <p class="italic text-stone-500">{{ __('The content was deleted.') }}</p>
                            @endif
                        </blockquote>
                        @if ($report->details)<p class="mt-2 text-sm text-stone-600">“{{ $report->details }}”</p>@endif
                        @if ($status === 'open')
                            <div class="mt-3 flex gap-2">
                                <form method="POST" action="{{ route('community.moderation.decide', $report) }}">@csrf<input type="hidden" name="decision" value="hide"><x-ui.button type="submit" size="sm" variant="danger" icon="eye-off">{{ __('Hide it') }}</x-ui.button></form>
                                <form method="POST" action="{{ route('community.moderation.decide', $report) }}">@csrf<input type="hidden" name="decision" value="dismiss"><x-ui.button type="submit" size="sm" variant="secondary">{{ __('Dismiss') }}</x-ui.button></form>
                            </div>
                        @else
                            <p class="mt-2 text-xs text-stone-500">{{ __('By :name, :when', ['name' => $report->handler?->name, 'when' => $report->handled_at?->diffForHumans()]) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</x-layouts.app>
