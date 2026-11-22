@props(['title' => null])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#13613f">
<title>{{ $title ? $title.' · ' : '' }}{{ config('napps.name') }} {{ __('Member Schools') }}</title>
@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
