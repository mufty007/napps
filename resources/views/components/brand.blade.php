@props(['compact' => false, 'inverse' => false, 'mark' => false])
{{-- Official NAPPS seal (circular emblem). Compact / mark = icon only. --}}
<span {{ $attributes->class('inline-flex items-center gap-2.5') }}>
    <img
        src="{{ asset('images/napps-icon.png') }}"
        alt=""
        width="36"
        height="36"
        @class(['size-9 shrink-0 rounded-full bg-white shadow-sm ring-1 ring-black/5', 'ring-white/20' => $inverse])
        decoding="async"
    >
    @unless ($compact || $mark)
        <span class="leading-tight">
            <span @class(['block text-[0.95rem] font-semibold tracking-tight', 'text-stone-900' => ! $inverse, 'text-white' => $inverse])>{{ config('napps.name') }}</span>
            <span @class(['block text-[0.7rem] font-medium', 'text-stone-500' => ! $inverse, 'text-brand-100' => $inverse])>{{ __('Member Schools') }}</span>
        </span>
    @endunless
</span>
