@props(['tabs' => [], 'active' => null])

<nav {{ $attributes->class('flex flex-wrap gap-1 border-b border-stone-200') }} aria-label="{{ __('Status filters') }}">
    @foreach ($tabs as $value => $tab)
        @php
            $label = is_array($tab) ? ($tab['label'] ?? $value) : $tab;
            $count = is_array($tab) ? ($tab['count'] ?? null) : null;
            $href = is_array($tab) ? ($tab['href'] ?? '#') : '#';
            $isActive = (string) $active === (string) $value;
        @endphp
        <a href="{{ $href }}"
            @class([
                'inline-flex min-h-11 items-center gap-2 border-b-2 px-3 text-sm font-semibold transition -mb-px',
                'border-brand-700 text-brand-800' => $isActive,
                'border-transparent text-stone-500 hover:text-stone-800' => ! $isActive,
            ])
            @if ($isActive) aria-current="page" @endif
            @if (! empty($tab['ajax'] ?? false)) data-ajax-tab="{{ $value }}" @endif
        >
            {{ $label }}
            @if ($count !== null && $count !== '')
                <span @class([
                    'rounded-full px-1.5 py-0.5 text-xs tabular',
                    'bg-brand-100 text-brand-800' => $isActive,
                    'bg-stone-100 text-stone-600' => ! $isActive,
                ])>{{ $count }}</span>
            @endif
        </a>
    @endforeach
</nav>
