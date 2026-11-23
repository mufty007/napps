@props(['action' => null, 'method' => 'GET'])

<form method="{{ $method }}" @if ($action) action="{{ $action }}" @endif {{ $attributes->class('flex flex-wrap items-end gap-3') }}>
    {{ $slot }}
</form>
