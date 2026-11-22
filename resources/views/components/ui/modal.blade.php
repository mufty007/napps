@props(['name', 'title' => null, 'maxWidth' => 'max-w-lg'])
{{--
    Open with: $dispatch('open-modal', 'name')   Close with: $dispatch('close-modal', 'name')
    Becomes a bottom sheet on phones.
--}}
<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
    x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-labelledby="modal-{{ $name }}-title" @endif
>
    <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-stone-950/40" x-on:click="open = false"></div>

    <div
        x-show="open"
        x-trap.noscroll="open"
        x-transition:enter="transition duration-200 ease-snappy"
        x-transition:enter-start="translate-y-6 opacity-0 sm:translate-y-2"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="translate-y-4 opacity-0"
        {{ $attributes->class('relative w-full rounded-t-3xl bg-white p-6 pb-safe shadow-xl sm:rounded-2xl '.$maxWidth) }}
    >
        @if ($title)
            <div class="mb-4 flex items-start justify-between gap-4">
                <h2 id="modal-{{ $name }}-title" class="text-lg font-semibold text-stone-900">{{ $title }}</h2>
                <button type="button" class="-m-2 flex size-11 items-center justify-center rounded-xl text-stone-500 hover:bg-stone-100" x-on:click="open = false">
                    <x-ui.icon name="x" />
                    <span class="sr-only">{{ __('Close') }}</span>
                </button>
            </div>
        @endif
        {{ $slot }}
    </div>
</div>
