@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <x-layouts.partials.head :title="$title" />
</head>
<body class="min-h-full bg-white font-sans" x-data>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">{{ __('Skip to content') }}</a>

    <header class="sticky top-0 z-30 border-b border-stone-200/70 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ route('home') }}"><x-brand /></a>
            <nav class="flex items-center gap-1 sm:gap-2" aria-label="{{ __('Main') }}">
                @auth
                    <x-ui.button :href="route('dashboard')" size="sm" icon-right="arrow-right">{{ __('Open dashboard') }}</x-ui.button>
                @else
                    <x-ui.button :href="route('login')" variant="ghost" size="sm">{{ __('Sign in') }}</x-ui.button>
                    <x-ui.button :href="route('register')" size="sm" class="hidden sm:inline-flex">{{ __('Create account') }}</x-ui.button>
                @endauth
            </nav>
        </div>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    <footer class="border-t border-stone-200 bg-stone-50">
        <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-8 text-sm text-stone-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <p>&copy; {{ now()->year }} {{ config('napps.full_name') }}</p>
            <p>{{ __('Members-only platform. Data handled under the Nigeria Data Protection Act 2023.') }}</p>
        </div>
    </footer>

    <x-ui.toaster />
</body>
</html>
