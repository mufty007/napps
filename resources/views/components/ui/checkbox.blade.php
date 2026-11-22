@props(['name', 'label', 'value' => '1', 'checked' => false, 'bag' => 'default'])
@php
    $id = $attributes->get('id', $name);
    $error = $errors->getBag($bag)->first($name);
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="flex min-h-11 cursor-pointer items-start gap-3 py-1">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="checkbox"
            value="{{ $value }}"
            @checked(old($name, $checked))
            {{ $attributes->except('class')->class('mt-0.5 size-5 rounded-md border-stone-300 text-brand-700 focus:ring-brand-600') }}
        />
        <span class="text-sm text-stone-700">{{ $label }}</span>
    </label>
    @if ($error)
        <p class="text-sm font-medium text-red-700">{{ $error }}</p>
    @endif
</div>
