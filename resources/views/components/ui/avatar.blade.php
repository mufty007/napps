@props(['user', 'size' => 'size-9'])

<span {{ $attributes->class($size.' inline-flex shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800 ring-2 ring-white') }} aria-hidden="true">
    {{ $user->initials() }}
</span>
