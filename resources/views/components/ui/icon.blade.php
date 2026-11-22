@props(['name', 'size' => 'size-5'])
{{-- Lucide icon, server-rendered SVG: <x-ui.icon name="wallet" /> --}}
@svg('lucide-'.$name, trim($size.' shrink-0 '.$attributes->get('class')), ['aria-hidden' => 'true', 'stroke-width' => $attributes->get('stroke-width', 1.75)])
