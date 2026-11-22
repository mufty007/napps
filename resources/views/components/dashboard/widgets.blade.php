@props(['widgets' => []])
{{-- Module cards registered through App\Support\Dashboard\DashboardWidgets. --}}
@foreach ($widgets as $widget)
    @include($widget)
@endforeach
