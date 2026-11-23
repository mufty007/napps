{{-- Compact pager: "1–15 of 42" with previous / next, page numbers from sm up. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination') }}" class="flex items-center justify-between gap-3 text-sm">
        <p class="text-stone-500">
            @if ($paginator->firstItem())
                {!! __(':from–:to of :total', ['from' => '<span class="font-semibold text-stone-800 tabular">'.$paginator->firstItem().'</span>', 'to' => '<span class="font-semibold text-stone-800 tabular">'.$paginator->lastItem().'</span>', 'total' => '<span class="font-semibold text-stone-800 tabular">'.$paginator->total().'</span>']) !!}
            @else
                {{ __('Page :page', ['page' => $paginator->currentPage()]) }}
            @endif
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="inline-flex size-11 items-center justify-center rounded-xl text-stone-300" aria-disabled="true"><x-ui.icon name="chevron-left" size="size-4" /><span class="sr-only">{{ __('Previous') }}</span></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex size-11 items-center justify-center rounded-xl text-stone-600 ring-1 ring-inset ring-stone-200 hover:bg-stone-50"><x-ui.icon name="chevron-left" size="size-4" /><span class="sr-only">{{ __('Previous') }}</span></a>
            @endif

            <span class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-2 text-stone-400">{{ $element }}</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="inline-flex h-11 min-w-11 items-center justify-center rounded-xl bg-brand-700 px-3 font-semibold text-white tabular">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="inline-flex h-11 min-w-11 items-center justify-center rounded-xl px-3 font-medium text-stone-600 tabular hover:bg-stone-100" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex size-11 items-center justify-center rounded-xl text-stone-600 ring-1 ring-inset ring-stone-200 hover:bg-stone-50"><x-ui.icon name="chevron-right" size="size-4" /><span class="sr-only">{{ __('Next') }}</span></a>
            @else
                <span class="inline-flex size-11 items-center justify-center rounded-xl text-stone-300" aria-disabled="true"><x-ui.icon name="chevron-right" size="size-4" /><span class="sr-only">{{ __('Next') }}</span></span>
            @endif
        </div>
    </nav>
@endif
