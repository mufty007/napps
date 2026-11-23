@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <x-layouts.partials.head :title="$title" />
</head>
<body class="min-h-full bg-white font-sans" x-data="{ navOpen: false }">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">{{ __('Skip to content') }}</a>

    <header class="sticky top-0 z-30 border-b border-stone-200/70 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="shrink-0"><x-brand /></a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('Main') }}">
                <a href="{{ route('news.index') }}" @class(['rounded-lg px-3 py-2 text-sm font-medium', request()->routeIs('news.*') ? 'text-brand-800 bg-brand-50' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'])>{{ __('News') }}</a>
                <a href="{{ route('events.index') }}" @class(['rounded-lg px-3 py-2 text-sm font-medium', request()->routeIs('events.index', 'events.show') ? 'text-brand-800 bg-brand-50' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'])>{{ __('Events') }}</a>
                <a href="{{ route('jobs.index') }}" @class(['rounded-lg px-3 py-2 text-sm font-medium', request()->routeIs('jobs.index', 'jobs.show') ? 'text-brand-800 bg-brand-50' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'])>{{ __('Jobs') }}</a>
                <a href="{{ route('join') }}" @class(['rounded-lg px-3 py-2 text-sm font-medium', request()->routeIs('join') ? 'text-brand-800 bg-brand-50' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'])>{{ __('Join as a school') }}</a>
                @if (Route::has('verify.index'))
                    <a href="{{ route('verify.index') }}" @class(['rounded-lg px-3 py-2 text-sm font-medium', request()->routeIs('verify.*') ? 'text-brand-800 bg-brand-50' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'])>{{ __('Verify') }}</a>
                @endif
            </nav>

            <div class="flex items-center gap-1 sm:gap-2">
                @auth
                    <x-ui.button :href="route('start')" size="sm" icon-right="arrow-right">{{ __('Open the app') }}</x-ui.button>
                @else
                    <x-ui.button :href="route('login')" variant="ghost" size="sm">{{ __('Sign in') }}</x-ui.button>
                    <x-ui.button :href="route('join')" size="sm" class="hidden sm:inline-flex">{{ __('Join') }}</x-ui.button>
                @endauth
                <button type="button" class="inline-flex size-11 items-center justify-center rounded-xl text-stone-600 hover:bg-stone-100 lg:hidden" @click="navOpen = ! navOpen" :aria-expanded="navOpen" aria-controls="mobile-nav">
                    <span class="sr-only">{{ __('Menu') }}</span>
                    <x-ui.icon name="menu" x-show="! navOpen" />
                    <x-ui.icon name="x" x-cloak x-show="navOpen" />
                </button>
            </div>
        </div>

        <div id="mobile-nav" x-cloak x-show="navOpen" x-transition class="border-t border-stone-200 bg-white lg:hidden">
            <nav class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-3 sm:px-6" aria-label="{{ __('Mobile') }}">
                <a href="{{ route('news.index') }}" class="rounded-xl px-3 py-3 text-sm font-medium text-stone-700 hover:bg-stone-100">{{ __('News & updates') }}</a>
                <a href="{{ route('events.index') }}" class="rounded-xl px-3 py-3 text-sm font-medium text-stone-700 hover:bg-stone-100">{{ __('Events & webinars') }}</a>
                <a href="{{ route('jobs.index') }}" class="rounded-xl px-3 py-3 text-sm font-medium text-stone-700 hover:bg-stone-100">{{ __('Job board') }}</a>
                <a href="{{ route('join') }}" class="rounded-xl px-3 py-3 text-sm font-medium text-stone-700 hover:bg-stone-100">{{ __('Join as a school') }}</a>
                @if (Route::has('verify.index'))
                    <a href="{{ route('verify.index') }}" class="rounded-xl px-3 py-3 text-sm font-medium text-stone-700 hover:bg-stone-100">{{ __('Verify a member school') }}</a>
                @endif
            </nav>
        </div>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    <footer class="border-t border-stone-200 bg-stone-50">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-[1.2fr_1fr]">
            <div>
                <x-brand />
                <p class="mt-3 max-w-md text-sm text-stone-600">{{ config('napps.full_name') }}. {{ config('napps.motto') }}.</p>
            </div>
            <div class="grid grid-cols-2 gap-6 text-sm">
                <div>
                    <p class="font-semibold text-stone-900">{{ __('Explore') }}</p>
                    <ul class="mt-3 space-y-2 text-stone-600">
                        <li><a href="{{ route('news.index') }}" class="hover:text-brand-800">{{ __('News & updates') }}</a></li>
                        <li><a href="{{ route('events.index') }}" class="hover:text-brand-800">{{ __('Events & webinars') }}</a></li>
                        <li><a href="{{ route('jobs.index') }}" class="hover:text-brand-800">{{ __('Job board') }}</a></li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-stone-900">{{ __('Members') }}</p>
                    <ul class="mt-3 space-y-2 text-stone-600">
                        <li><a href="{{ route('join') }}" class="hover:text-brand-800">{{ __('Join as a school') }}</a></li>
                        @if (Route::has('verify.index'))
                            <li><a href="{{ route('verify.index') }}" class="hover:text-brand-800">{{ __('Verify a school') }}</a></li>
                        @endif
                        <li><a href="{{ route('login') }}" class="hover:text-brand-800">{{ __('Sign in') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="border-t border-stone-200">
            <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-sm text-stone-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p>&copy; {{ now()->year }} {{ config('napps.full_name') }}</p>
                <p>{{ __('Members-only platform. Data handled under the Nigeria Data Protection Act 2023.') }}</p>
            </div>
        </div>
    </footer>

    <x-ui.toaster />
</body>
</html>
