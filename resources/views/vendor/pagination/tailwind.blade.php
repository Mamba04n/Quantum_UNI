@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="mt-4">

        {{-- Mobile --}}
        <div class="flex gap-2 items-center justify-between sm:hidden">

            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-slate-400 bg-slate-50 border border-slate-200 cursor-not-allowed rounded-lg">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Anterior
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:border-slate-300 transition-all duration-150 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Anterior
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:border-slate-300 transition-all duration-150 shadow-sm">
                    Siguiente
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-slate-400 bg-slate-50 border border-slate-200 cursor-not-allowed rounded-lg">
                    Siguiente
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            @endif

        </div>

        {{-- Desktop --}}
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between gap-4">

            <div>
                <p class="text-xs text-slate-500 font-medium">
                    Mostrando
                    @if ($paginator->firstItem())
                        <span class="font-black text-slate-800">{{ $paginator->firstItem() }}</span>
                        al
                        <span class="font-black text-slate-800">{{ $paginator->lastItem() }}</span>
                    @else
                        <span class="font-black text-slate-800">{{ $paginator->count() }}</span>
                    @endif
                    de
                    <span class="font-black text-slate-800">{{ $paginator->total() }}</span>
                    resultados
                </p>
            </div>

            <div>
                <span class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white shadow-sm px-1 py-1">

                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true">
                            <span class="inline-flex items-center justify-center w-8 h-8 text-slate-300 cursor-not-allowed rounded-lg" aria-hidden="true">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center justify-center w-8 h-8 text-slate-500 rounded-lg hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150" aria-label="{{ __('pagination.previous') }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)

                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span class="inline-flex items-center justify-center w-8 h-8 text-xs font-bold text-slate-400 cursor-default">{{ $element }}</span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="inline-flex items-center justify-center w-8 h-8 text-xs font-black text-white bg-slate-900 rounded-lg cursor-default">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="inline-flex items-center justify-center w-8 h-8 text-xs font-bold text-slate-600 rounded-lg hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center justify-center w-8 h-8 text-slate-500 rounded-lg hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150" aria-label="{{ __('pagination.next') }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @else
                        <span aria-disabled="true">
                            <span class="inline-flex items-center justify-center w-8 h-8 text-slate-300 cursor-not-allowed rounded-lg" aria-hidden="true">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        </span>
                    @endif

                </span>
            </div>
        </div>
    </nav>
@endif
