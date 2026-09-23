@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('app.pagination.label') }}" class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
        <p class="text-sm text-slate-600">
            {{ __('app.pagination.showing', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>

        <ul class="flex flex-wrap items-center justify-center gap-1">
            @if ($paginator->onFirstPage())
                <li aria-disabled="true">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-400" aria-hidden="true">
                        <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('app.pagination.previous') }}"
                       class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                        <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li aria-disabled="true"><span class="flex h-10 min-w-10 items-center justify-center px-2 text-slate-500">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li aria-current="page">
                                <span class="flex h-10 min-w-10 items-center justify-center rounded-lg bg-brand-700 px-3 text-sm font-bold text-white">{{ $page }}</span>
                            </li>
                        @else
                            <li>
                                <a href="{{ $url }}" aria-label="{{ __('app.pagination.goto', ['page' => $page]) }}"
                                   class="flex h-10 min-w-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 hover:bg-slate-50">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('app.pagination.next') }}"
                       class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                        <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </li>
            @else
                <li aria-disabled="true">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-400" aria-hidden="true">
                        <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </span>
                </li>
            @endif
        </ul>
    </nav>
@endif
