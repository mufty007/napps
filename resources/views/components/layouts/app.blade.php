@props(['title' => null])
{{--
    Signed-in shell. Navigation comes from App\Support\Navigation\Navigation
    (filtered to the active role), so the executive, school, parent and legal
    shells share one frame: sidebar on desktop, bottom bar on phones.
--}}
@php
    $user = auth()->user();
    $assignment = $context->assignment();
    $role = $context->role();

    $mobile = $navItems->where('mobile', true);
    $community = $mobile->firstWhere('key', 'community');
    $bottom = $mobile->reject(fn ($item) => $item->key === 'community')->take($community ? 3 : 4);
    if ($community) {
        $bottom->push($community);
    }
    $hasOverflow = $navItems->count() > $bottom->count();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-layouts.partials.head :title="$title" />
</head>
<body class="min-h-full font-sans" x-data="{ menu: false }">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">{{ __('Skip to content') }}</a>

    {{-- Desktop sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-stone-200 bg-white lg:flex">
        <div class="flex h-16 items-center px-5">
            <a href="{{ route('dashboard') }}"><x-brand /></a>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="{{ __('Main') }}">
            @foreach ($navItems as $item)
                @php $active = request()->routeIs($item->activePattern()); @endphp
                <a
                    href="{{ route($item->route) }}"
                    @class([
                        'flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors',
                        'bg-brand-50 text-brand-800' => $active,
                        'text-stone-600 hover:bg-stone-100 hover:text-stone-900' => ! $active,
                    ])
                    @if ($active) aria-current="page" @endif
                >
                    <x-ui.icon :name="$item->icon" :class="$active ? 'text-brand-700' : 'text-stone-400'" />
                    {{ __($item->label) }}
                </a>
            @endforeach
        </nav>

        <div class="border-t border-stone-200 p-3">
            <a href="{{ route('account.show') }}" class="flex items-center gap-3 rounded-xl p-2 hover:bg-stone-100">
                <x-ui.avatar :user="$user" />
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-stone-900">{{ $user->name }}</span>
                    <span class="block truncate text-xs text-stone-500">{{ __('Account & security') }}</span>
                </span>
            </a>
        </div>
    </aside>

    <div class="flex min-h-screen flex-col lg:pl-64">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 border-b border-stone-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                <a href="{{ route('dashboard') }}" class="lg:hidden"><x-brand compact /></a>

                {{-- Role switcher --}}
                <div class="min-w-0 flex-1 lg:flex-none">
                    @if ($context->assignments()->count() > 1)
                        <x-ui.dropdown align="left" width="w-72">
                            <x-slot:trigger>
                                <button type="button" class="flex min-h-11 max-w-full items-center gap-2 rounded-xl px-3 text-left hover:bg-stone-100">
                                    <span class="min-w-0">
                                        <span class="block text-[0.7rem] font-semibold uppercase tracking-wider text-stone-500">{{ __('Working as') }}</span>
                                        <span class="block truncate text-sm font-semibold text-stone-900">{{ $assignment->label() }}</span>
                                    </span>
                                    <x-ui.icon name="chevrons-up-down" size="size-4" class="text-stone-400" />
                                </button>
                            </x-slot:trigger>

                            <p class="px-3 pb-1 pt-2 text-xs font-semibold text-stone-500">{{ __('Switch role') }}</p>
                            @foreach ($context->assignments() as $option)
                                <form method="POST" action="{{ route('context.update') }}">
                                    @csrf
                                    <input type="hidden" name="assignment" value="{{ $option->id }}">
                                    <x-ui.dropdown-item :active="$option->is($assignment)">{{ $option->label() }}</x-ui.dropdown-item>
                                </form>
                            @endforeach
                        </x-ui.dropdown>
                    @elseif ($assignment)
                        <div class="px-3">
                            <span class="block text-[0.7rem] font-semibold uppercase tracking-wider text-stone-500">{{ __('Working as') }}</span>
                            <span class="block truncate text-sm font-semibold text-stone-900">{{ $assignment->label() }}</span>
                        </div>
                    @endif
                </div>

                {{-- User menu --}}
                <x-ui.dropdown>
                    <x-slot:trigger>
                        <button type="button" class="flex size-11 items-center justify-center rounded-full hover:bg-stone-100">
                            <x-ui.avatar :user="$user" />
                            <span class="sr-only">{{ __('Open account menu') }}</span>
                        </button>
                    </x-slot:trigger>

                    <div class="px-3 py-2">
                        <p class="truncate text-sm font-semibold text-stone-900">{{ $user->name }}</p>
                        <p class="truncate text-xs text-stone-500">{{ \App\Support\PhoneNumber::format($user->phone) }}</p>
                    </div>
                    <x-ui.dropdown-item :href="route('account.show')" icon="shield-check">{{ __('Account & security') }}</x-ui.dropdown-item>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.dropdown-item icon="log-out">{{ __('Sign out') }}</x-ui.dropdown-item>
                    </form>
                </x-ui.dropdown>
            </div>
        </header>

        <main id="main" class="mx-auto w-full max-w-6xl flex-1 px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-12">
            {{ $slot }}
        </main>
    </div>

    {{-- Phone bottom bar --}}
    @if ($navItems->count() > 1)
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white/95 pb-safe backdrop-blur lg:hidden" aria-label="{{ __('Main') }}">
            <div class="mx-auto flex max-w-md items-stretch justify-around px-2 pt-1.5">
                @foreach ($bottom as $item)
                    @php $active = request()->routeIs($item->activePattern()); @endphp
                    <a
                        href="{{ route($item->route) }}"
                        @class([
                            'flex min-h-12 min-w-16 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl text-[0.7rem] font-semibold',
                            'text-brand-700' => $active,
                            'text-stone-500' => ! $active,
                        ])
                        @if ($active) aria-current="page" @endif
                    >
                        <x-ui.icon :name="$item->icon" size="size-[22px]" />
                        {{ __($item->label) }}
                    </a>
                @endforeach

                @if ($hasOverflow)
                    <button type="button" x-on:click="menu = true" class="flex min-h-12 min-w-16 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl text-[0.7rem] font-semibold text-stone-500">
                        <x-ui.icon name="menu" size="size-[22px]" />
                        {{ __('More') }}
                    </button>
                @endif
            </div>
        </nav>

        @if ($hasOverflow)
            <div x-show="menu" x-cloak class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('All screens') }}">
                <div x-show="menu" x-transition.opacity.duration.200ms class="absolute inset-0 bg-stone-950/40" x-on:click="menu = false"></div>
                <div
                    x-show="menu"
                    x-transition:enter="transition duration-200 ease-snappy"
                    x-transition:enter-start="translate-y-full"
                    x-transition:enter-end="translate-y-0"
                    x-transition:leave="transition duration-150 ease-in"
                    x-transition:leave-start="translate-y-0"
                    x-transition:leave-end="translate-y-full"
                    class="absolute inset-x-0 bottom-0 rounded-t-3xl bg-white p-4 pb-safe shadow-xl"
                >
                    <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-stone-300" aria-hidden="true"></div>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($navItems as $item)
                            <a href="{{ route($item->route) }}" class="flex min-h-20 flex-col items-center justify-center gap-1.5 rounded-2xl bg-stone-50 p-2 text-center text-xs font-semibold text-stone-700">
                                <x-ui.icon :name="$item->icon" class="text-brand-700" />
                                {{ __($item->label) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif

    <x-ui.toaster />
</body>
</html>
