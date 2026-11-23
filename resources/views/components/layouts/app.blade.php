@props(['title' => null])
{{--
    Signed-in shell. Navigation comes from App\Support\Navigation\Navigation
    (filtered to the active role and grouped into sections), so the executive,
    school, parent and legal shells share one frame: a collapsible sidebar on
    desktop, a bottom bar plus "More" sheet on phones.

    Collapsed state lives in the napps_sidebar cookie and is rendered by the
    server as data-sidebar on <body>; every width/label switch is a CSS variant
    on that attribute, so the page never flashes the wrong layout.
--}}
@php
    $user = auth()->user();
    $assignment = $context->assignment();
    $role = $context->role();
    $collapsed = request()->cookie('napps_sidebar') === '1';
    $sections = app(\App\Support\Navigation\Navigation::class)->sections($navItems);

    // Phone bottom bar: social screens first (like Facebook / LinkedIn), then the rest.
    $mobile = $navItems->where('mobile', true);
    $preferred = collect(['home', 'community.chat', 'community.groups', 'dashboard'])->map(fn ($key) => $mobile->firstWhere('key', $key))->filter();
    $bottom = $preferred->merge($mobile->reject(fn ($item) => $preferred->contains('key', $item->key)))->take(4)->values();
    $hasOverflow = $navItems->count() > $bottom->count();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-layouts.partials.head :title="$title" />
</head>
<body
    class="group/shell min-h-full font-sans"
    data-user-name="{{ Str::before($user->name, ' ') }}"
    data-sidebar="{{ $collapsed ? 'collapsed' : 'expanded' }}"
    x-data="{
        menu: false,
        collapsed: @js($collapsed),
        toggleSidebar() {
            this.collapsed = ! this.collapsed
            document.cookie = 'napps_sidebar=' + (this.collapsed ? '1' : '0') + '; path=/; max-age=31536000; samesite=lax'
        },
    }"
    x-bind:data-sidebar="collapsed ? 'collapsed' : 'expanded'"
>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">{{ __('Skip to content') }}</a>

    {{-- Desktop sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-brand-950 bg-brand-900 text-white transition-[width] duration-200 ease-snappy lg:flex group-data-[sidebar=collapsed]/shell:w-[4.5rem]">
        <div class="flex h-16 shrink-0 items-center px-5 group-data-[sidebar=collapsed]/shell:justify-center group-data-[sidebar=collapsed]/shell:px-0">
            <a href="{{ route('start') }}" class="min-w-0 rounded-xl" aria-label="{{ __('Home') }}">
                <span class="block group-data-[sidebar=collapsed]/shell:hidden"><x-brand inverse /></span>
                <span class="hidden group-data-[sidebar=collapsed]/shell:block"><x-brand mark inverse /></span>
            </a>
        </div>

        <nav class="scrollbar-dark flex-1 overflow-y-auto overscroll-contain px-3 pb-4 group-data-[sidebar=collapsed]/shell:px-2" aria-label="{{ __('Main') }}">
            @foreach ($sections as $section)
                @if ($section['label'])
                    <p class="px-3 pb-1.5 pt-5 text-[0.7rem] font-semibold uppercase tracking-wider text-brand-200/70 group-data-[sidebar=collapsed]/shell:hidden">{{ __($section['label']) }}</p>
                    <div class="mx-3 my-3 hidden h-px bg-white/15 group-data-[sidebar=collapsed]/shell:block" aria-hidden="true"></div>
                @endif
                <ul class="space-y-0.5">
                    @foreach ($section['items'] as $item)
                        @php $active = request()->routeIs(...$item->patterns()); @endphp
                        <li>
                            <a
                                href="{{ route($item->route) }}"
                                @class([
                                    'relative flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors',
                                    'group-data-[sidebar=collapsed]/shell:justify-center group-data-[sidebar=collapsed]/shell:px-0',
                                    'bg-white/15 text-white' => $active,
                                    'text-white/80 hover:bg-white/10 hover:text-white' => ! $active,
                                ])
                                @if ($active) aria-current="page" @endif
                                x-bind:title="collapsed ? @js(__($item->label)) : null"
                            >
                                @if ($active)
                                    <span class="absolute inset-y-2 left-0 w-1 rounded-full bg-accent-400 group-data-[sidebar=collapsed]/shell:hidden" aria-hidden="true"></span>
                                @endif
                                <x-ui.icon :name="$item->icon" :class="$active ? 'text-white' : 'text-white/70'" />
                                <span class="truncate group-data-[sidebar=collapsed]/shell:sr-only">{{ __($item->label) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <div class="shrink-0 space-y-1 border-t border-white/10 p-2">
            <a
                href="{{ route('account.show') }}"
                class="flex items-center gap-3 rounded-xl p-2 hover:bg-white/10 group-data-[sidebar=collapsed]/shell:justify-center"
                x-bind:title="collapsed ? @js($user->name) : null"
            >
                <x-ui.avatar :user="$user" />
                <span class="min-w-0 group-data-[sidebar=collapsed]/shell:sr-only">
                    <span class="block truncate text-sm font-semibold text-white">{{ $user->name }}</span>
                    <span class="block truncate text-xs text-white/60">{{ __('Account & security') }}</span>
                </span>
            </a>
            <button
                type="button"
                class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white group-data-[sidebar=collapsed]/shell:justify-center group-data-[sidebar=collapsed]/shell:px-0"
                x-on:click="toggleSidebar()"
                x-bind:aria-expanded="(! collapsed).toString()"
                aria-label="{{ __('Collapse sidebar') }}"
                x-bind:aria-label="collapsed ? @js(__('Expand sidebar')) : @js(__('Collapse sidebar'))"
            >
                <x-ui.icon name="panel-left-close" class="group-data-[sidebar=collapsed]/shell:hidden" />
                <x-ui.icon name="panel-left-open" class="hidden group-data-[sidebar=collapsed]/shell:block" />
                <span class="group-data-[sidebar=collapsed]/shell:sr-only">{{ __('Collapse sidebar') }}</span>
            </button>
        </div>
    </aside>

    <div class="flex min-h-screen flex-col transition-[padding] duration-200 ease-snappy lg:pl-64 lg:group-data-[sidebar=collapsed]/shell:pl-[4.5rem]">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 border-b border-stone-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-2 px-4 sm:gap-3 sm:px-6 lg:px-8">
                <a href="{{ route('dashboard') }}" class="shrink-0 lg:hidden" aria-label="{{ __('Home') }}"><x-brand mark /></a>

                {{-- Role switcher --}}
                <div class="min-w-0 flex-1">
                    @if ($context->assignments()->count() > 1)
                        <x-ui.dropdown align="left" width="w-72 max-w-[calc(100vw-2rem)]">
                            <x-slot:trigger>
                                <button type="button" class="-ml-1 flex min-h-11 max-w-full items-center gap-2 rounded-xl px-2 text-left hover:bg-stone-100 sm:px-3">
                                    <span class="min-w-0">
                                        <span class="block text-[0.65rem] font-semibold uppercase tracking-wider text-stone-500">{{ __('Working as') }}</span>
                                        <span class="block truncate text-sm font-semibold text-stone-900">{{ $assignment->label() }}</span>
                                    </span>
                                    <x-ui.icon name="chevrons-up-down" size="size-4" class="text-stone-400" />
                                </button>
                            </x-slot:trigger>

                            <p class="px-3 pb-1 pt-2 text-xs font-semibold text-stone-500">{{ __('Switch role') }}</p>
                            @foreach ($context->assignments() as $option)
                                <form method="POST" action="{{ route('context.update') }}" x-data="ajaxForm()" @submit.prevent="submit($el)">
                                    @csrf
                                    <input type="hidden" name="assignment" value="{{ $option->id }}">
                                    <input type="hidden" name="redirect" value="{{ url()->current() }}">
                                    <x-ui.dropdown-item :active="$option->is($assignment)" :disabled="$option->is($assignment)">{{ $option->label() }}</x-ui.dropdown-item>
                                </form>
                            @endforeach
                        </x-ui.dropdown>
                    @elseif ($assignment)
                        <div class="min-w-0 px-1 sm:px-2">
                            <span class="block text-[0.65rem] font-semibold uppercase tracking-wider text-stone-500">{{ __('Working as') }}</span>
                            <span class="block truncate text-sm font-semibold text-stone-900">{{ $assignment->label() }}</span>
                        </div>
                    @endif
                </div>

                <a
                    href="{{ route('notifications.index') }}"
                    @class([
                        'relative flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-stone-100',
                        'bg-brand-50 text-brand-700' => request()->routeIs('notifications.*'),
                        'text-stone-600' => ! request()->routeIs('notifications.*'),
                    ])
                >
                    <x-ui.icon name="bell" />
                    <span data-unread-badge @class(['absolute right-1 top-1 flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[0.65rem] font-bold leading-5 text-white ring-2 ring-white tabular', 'hidden' => $unreadNotifications < 1]) aria-hidden="true">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                    <span class="sr-only">{{ trans_choice('Notifications (:count unread)|Notifications (:count unread)', $unreadNotifications) }}</span>
                </a>

                <x-ui.dropdown>
                    <x-slot:trigger>
                        <button type="button" class="flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-stone-100">
                            <x-ui.avatar :user="$user" />
                            <span class="sr-only">{{ __('Open account menu') }}</span>
                        </button>
                    </x-slot:trigger>

                    <div class="px-3 py-2">
                        <p class="truncate text-sm font-semibold text-stone-900">{{ $user->name }}</p>
                        <p class="truncate text-xs text-stone-500">{{ \App\Support\PhoneNumber::format($user->phone) }}</p>
                    </div>
                    <x-ui.dropdown-item :href="route('account.show')" icon="shield-check">{{ __('Account & security') }}</x-ui.dropdown-item>
                    <x-ui.dropdown-item :href="route('notifications.index')" icon="bell">{{ __('Notification settings') }}</x-ui.dropdown-item>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.dropdown-item icon="log-out">{{ __('Sign out') }}</x-ui.dropdown-item>
                    </form>
                </x-ui.dropdown>
            </div>
        </header>

        <main id="main" class="mx-auto w-full min-w-0 max-w-6xl flex-1 px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-12">
            {{ $slot }}
        </main>
    </div>

    {{-- Phone bottom bar --}}
    @if ($navItems->count() > 1)
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white/95 pb-safe backdrop-blur lg:hidden" aria-label="{{ __('Main') }}">
            <ul class="mx-auto flex max-w-md items-stretch justify-around px-1 pt-1">
                @foreach ($bottom as $item)
                    @php $active = request()->routeIs(...$item->patterns()); @endphp
                    <li class="flex min-w-0 flex-1">
                        <a
                            href="{{ route($item->route) }}"
                            @class([
                                'flex min-h-14 w-full min-w-0 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[0.7rem] font-semibold',
                                'text-brand-700' => $active,
                                'text-stone-500' => ! $active,
                            ])
                            @if ($active) aria-current="page" @endif
                        >
                            <span @class(['flex h-7 w-12 items-center justify-center rounded-full', 'bg-brand-50' => $active])>
                                <x-ui.icon :name="$item->icon" size="size-[22px]" />
                            </span>
                            <span class="line-clamp-2 max-w-full text-center text-[0.65rem] leading-[1.15]">{{ __($item->label) }}</span>
                        </a>
                    </li>
                @endforeach

                @if ($hasOverflow)
                    <li class="flex min-w-0 flex-1">
                        <button type="button" x-on:click="menu = true" class="flex min-h-14 w-full flex-col items-center justify-center gap-1 rounded-xl px-1 text-[0.7rem] font-semibold text-stone-500" x-bind:aria-expanded="menu.toString()">
                            <span class="flex h-7 w-12 items-center justify-center rounded-full"><x-ui.icon name="layout-grid" size="size-[22px]" /></span>
                            {{ __('More') }}
                        </button>
                    </li>
                @endif
            </ul>
        </nav>

        @if ($hasOverflow)
            <div x-show="menu" x-cloak class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('All screens') }}" x-on:keydown.escape.window="menu = false">
                <div x-show="menu" x-transition.opacity.duration.200ms class="absolute inset-0 bg-stone-950/40" x-on:click="menu = false"></div>
                <div
                    x-show="menu"
                    x-trap.noscroll="menu"
                    x-transition:enter="transition duration-200 ease-snappy"
                    x-transition:enter-start="translate-y-full"
                    x-transition:enter-end="translate-y-0"
                    x-transition:leave="transition duration-150 ease-in"
                    x-transition:leave-start="translate-y-0"
                    x-transition:leave-end="translate-y-full"
                    class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-3xl bg-white px-4 pb-safe pt-3 shadow-xl"
                >
                    <div class="mx-auto mb-2 h-1 w-10 rounded-full bg-stone-300" aria-hidden="true"></div>
                    <div class="flex items-center justify-between">
                        <p class="text-base font-semibold text-stone-900">{{ __('All screens') }}</p>
                        <button type="button" class="-mr-2 flex size-11 items-center justify-center rounded-xl text-stone-500 hover:bg-stone-100" x-on:click="menu = false">
                            <x-ui.icon name="x" />
                            <span class="sr-only">{{ __('Close') }}</span>
                        </button>
                    </div>
                    @foreach ($sections as $section)
                        @if ($section['label'])
                            <p class="pb-2 pt-4 text-[0.7rem] font-semibold uppercase tracking-wider text-stone-400">{{ __($section['label']) }}</p>
                        @endif
                        <div class="grid grid-cols-3 gap-2 {{ $section['label'] ? '' : 'pt-3' }}">
                            @foreach ($section['items'] as $item)
                                @php $active = request()->routeIs(...$item->patterns()); @endphp
                                <a href="{{ route($item->route) }}" @class([
                                    'flex min-h-20 flex-col items-center justify-center gap-1.5 rounded-2xl p-2 text-center text-xs font-semibold',
                                    'bg-brand-50 text-brand-800 ring-1 ring-brand-200' => $active,
                                    'bg-stone-50 text-stone-700' => ! $active,
                                ])>
                                    <x-ui.icon :name="$item->icon" class="text-brand-700" />
                                    {{ __($item->label) }}
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                    <div class="h-4"></div>
                </div>
            </div>
        @endif
    @endif

    <x-ui.toaster />
</body>
</html>
