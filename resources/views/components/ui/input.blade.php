@props([
    'name',
    'label' => null,
    'type' => 'text',
    'hint' => null,
    'value' => null,
    'bag' => 'default',
    'prefix' => null,
])
@php
    $id = $attributes->get('id', $name);
    $error = $errors->getBag($bag)->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp

<div {{ $attributes->only('class')->class('space-y-1.5') }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-stone-800">
            {{ $label }}
            @if ($attributes->has('required'))<span class="text-red-600" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <div class="relative">
        @if ($prefix)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm text-stone-500">{{ $prefix }}</span>
        @endif
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except('class')->class([
                'block w-full min-h-11 rounded-xl border-0 bg-white py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset placeholder:text-stone-400 focus:ring-2 focus:ring-inset sm:text-sm',
                'px-3.5' => ! $prefix,
                'pl-16 pr-3.5' => $prefix,
                'ring-stone-300 focus:ring-brand-600' => ! $error,
                'ring-red-400 focus:ring-red-600' => $error,
            ]) }}
        />
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-stone-500">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="text-sm font-medium text-red-700">{{ $error }}</p>
    @endif
</div>
