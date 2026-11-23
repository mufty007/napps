@props(['lines' => 3, 'class' => ''])

<div {{ $attributes->class('animate-pulse space-y-3 '.$class) }} aria-hidden="true">
    @for ($i = 0; $i < $lines; $i++)
        <div @class([
            'h-4 rounded-lg bg-stone-200/80',
            'w-3/4' => $i === 0,
            'w-full' => $i > 0 && $i < $lines - 1,
            'w-1/2' => $i === $lines - 1 && $lines > 1,
        ])></div>
    @endfor
</div>
