@props(['items' => []])

<ol {{ $attributes->class('relative space-y-0') }}>
    @forelse ($items as $i => $item)
        @php
            $state = $item['state'] ?? 'next'; // done | current | next
            $title = $item['title'] ?? '';
            $meta = $item['meta'] ?? null;
            $body = $item['body'] ?? null;
        @endphp
        <li class="relative flex gap-3 pb-4 last:pb-0" @if ($state === 'current') aria-current="step" @endif>
            @unless ($loop->last)
                <span @class([
                    'absolute left-[0.6875rem] top-6 h-[calc(100%-1.25rem)] w-px',
                    'bg-brand-600' => $state === 'done',
                    'bg-stone-200' => $state !== 'done',
                ]) aria-hidden="true"></span>
            @endunless
            <span @class([
                'relative flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                'bg-brand-700 text-white' => $state === 'done',
                'bg-accent-400 text-stone-900 ring-4 ring-accent-100' => $state === 'current',
                'bg-white text-stone-400 ring-1 ring-inset ring-stone-300' => $state === 'next',
            ])>
                @if ($state === 'done')
                    <x-ui.icon name="check" size="size-3.5" />
                @else
                    {{ $i + 1 }}
                @endif
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <p @class([
                    'text-sm',
                    'font-semibold text-stone-900' => $state === 'current',
                    'text-stone-700' => $state === 'done',
                    'text-stone-400' => $state === 'next',
                ])>{{ $title }}</p>
                @if ($meta)
                    <p class="mt-0.5 text-xs text-stone-500">{{ $meta }}</p>
                @endif
                @if ($body)
                    <p class="mt-1 whitespace-pre-wrap text-sm text-stone-600">{{ $body }}</p>
                @endif
            </div>
        </li>
    @empty
        <li class="text-sm text-stone-500">{{ __('No activity yet.') }}</li>
    @endforelse
</ol>
