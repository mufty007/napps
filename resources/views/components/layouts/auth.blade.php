@props(['title' => null, 'heading' => null, 'subheading' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-layouts.partials.head :title="$title ?? $heading" />
</head>
<body class="min-h-full font-sans">
    <div class="flex min-h-screen flex-col lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <aside class="relative hidden overflow-hidden bg-brand-900 text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div aria-hidden="true" class="absolute -right-32 -top-32 size-[28rem] rounded-full bg-brand-700/40 blur-3xl"></div>
            <div aria-hidden="true" class="absolute -bottom-40 -left-24 size-[26rem] rounded-full bg-accent-500/15 blur-3xl"></div>

            <a href="{{ route('home') }}" class="relative"><x-brand inverse /></a>

            <div class="relative max-w-md">
                <p class="text-3xl font-semibold leading-tight tracking-tight">
                    {{ __('One home for every NAPPS member school.') }}
                </p>
                <p class="mt-4 text-brand-100">
                    {{ __('Dues, fees, records, claims and community, from your chapter to the national secretariat.') }}
                </p>
            </div>

            <p class="relative text-sm text-brand-200">{{ config('napps.full_name') }}</p>
        </aside>

        <main class="flex flex-1 flex-col px-4 py-8 sm:px-8 lg:justify-center lg:px-16">
            <a href="{{ route('home') }}" class="mb-10 lg:hidden"><x-brand /></a>

            <div class="mx-auto w-full max-w-sm">
                @if ($heading)
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900">{{ $heading }}</h1>
                @endif
                @if ($subheading)
                    <p class="mt-2 text-sm text-stone-600">{{ $subheading }}</p>
                @endif

                <div class="mt-8">
                    {{ $slot }}
                </div>
            </div>
        </main>
    </div>

    <x-ui.toaster />
</body>
</html>
