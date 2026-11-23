@props(['name', 'size' => 'size-5'])
{{-- Lucide icon, server-rendered SVG: <x-ui.icon name="wallet" />. Extra attributes (x-show, x-cloak...) reach the <svg>. --}}
@svg('lucide-'.$name, trim($size.' shrink-0 '.$attributes->get('class')), array_merge(
    ['aria-hidden' => 'true', 'stroke-width' => $attributes->get('stroke-width', 1.75)],
    $attributes->except(['class', 'stroke-width'])->getAttributes(),
))
