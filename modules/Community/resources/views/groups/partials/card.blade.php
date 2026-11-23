@php
    $icon = match ($group->type) { 'national' => 'flag', 'state' => 'map', 'chapter' => 'map-pin', 'committee' => 'lock', default => 'users' };
    $url = $group->isAutomatic() ? route('community.feed', ['space' => $group->id]) : route('community.groups.show', $group);
@endphp
<div class="flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200/80">
    <a href="{{ $url }}" class="flex items-start gap-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"><x-ui.icon :name="$icon" /></span>
        <span class="min-w-0">
            <span class="block font-semibold text-stone-900">{{ $group->name }}</span>
            <span class="block text-xs text-stone-500">{{ $group->typeLabel() }}@if ($group->isGroup()) · {{ $group->privacyLabel() }}@endif @if ($count !== null) · {{ trans_choice(':count member|:count members', $count) }}@endif</span>
        </span>
    </a>
    @if ($group->description)
        <p class="mt-3 line-clamp-2 text-sm text-stone-600">{{ $group->description }}</p>
    @endif
    @if ($join ?? false)
        <form method="POST" action="{{ route('community.groups.join', $group) }}" class="mt-4">@csrf
            <x-ui.button type="submit" size="sm" variant="secondary" class="w-full">{{ $group->privacy === 'request' ? __('Ask to join') : __('Join') }}</x-ui.button>
        </form>
    @endif
</div>
