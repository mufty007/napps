@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'hint' => null,
    'bag' => 'default',
])
@php
    $id = $attributes->get('id', $name);
    $error = $errors->getBag($bag)->first($name);
    $selected = (string) old($name, $value);
@endphp

<div {{ $attributes->only('class')->class('space-y-1.5') }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-stone-800">{{ $label }}</label>
    @endif

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('class')->class([
            'block w-full min-h-11 rounded-xl border-0 bg-white py-2.5 pl-3.5 pr-10 text-base text-stone-900 shadow-sm ring-1 ring-inset focus:ring-2 focus:ring-inset sm:text-sm',
            'ring-stone-300 focus:ring-brand-600' => ! $error,
            'ring-red-400 focus:ring-red-600' => $error,
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>

    @if ($hint)
        <p class="text-sm text-stone-500">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="text-sm font-medium text-red-700">{{ $error }}</p>
    @endif
</div>
