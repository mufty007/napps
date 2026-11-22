{{-- @include('membership::partials.file-input', ['name' => ..., 'label' => ..., 'required' => bool, 'hint' => ..., 'accept' => ...]) --}}
@php
    $error = $errors->first($name);
    $describedBy = trim($name.'-hint '.($error ? $name.'-error' : ''));
@endphp
<div class="space-y-1.5">
    <label for="{{ $name }}" class="block text-sm font-medium text-stone-800">
        {{ $label }}
        @if ($required ?? false)<span class="text-red-600" aria-hidden="true">*</span>@else<span class="font-normal text-stone-500">({{ __('optional') }})</span>@endif
    </label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="file"
        accept="{{ $accept ?? 'application/pdf,image/jpeg,image/png,image/webp' }}"
        @if ($required ?? false) required @endif
        aria-describedby="{{ $describedBy }}"
        @if ($error) aria-invalid="true" @endif
        class="block w-full rounded-xl text-sm text-stone-700 ring-1 ring-inset ring-stone-300 file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-l-xl file:border-0 file:bg-brand-50 file:px-4 file:text-sm file:font-semibold file:text-brand-800 hover:file:bg-brand-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
    />
    <p id="{{ $name }}-hint" class="text-sm text-stone-500">{{ $hint }}</p>
    @if ($error)
        <p id="{{ $name }}-error" class="text-sm font-medium text-red-700">{{ $error }}</p>
    @endif
</div>
