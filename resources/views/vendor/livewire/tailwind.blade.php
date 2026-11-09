@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between mt-4">

            {{-- Mobile --}}
            <div class="flex justify-between flex-1 sm:hidden gap-2">
                @if ($paginator->onFirstPage())
                    <span class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-slate-400 bg-slate-50 border border-slate-200 cursor-not-allowed rounded-lg">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Anterior
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:border-slate-300 transition-all duration-150 shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Anterior
                    </button>
                @endif

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:border-slate-300 transition-all duration-150 shadow-sm">
                        Siguiente
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                @else
                    <span class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-slate-400 bg-slate-50 border border-slate-200 cursor-not-allowed rounded-lg">
                        Siguiente
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </span>
                @endif
            </div>

            {{-- Desktop --}}
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between gap-4">

                <div>
                    <p class="text-xs text-slate-500 font-medium">
                        Mostrando
                        <span class="font-black text-slate-800">{{ $paginator->firstItem() }}</span>
                        al
                        <span class="font-black text-slate-800">{{ $paginator->lastItem() }}</span>
                        de
                        <span class="font-black text-slate-800">{{ $paginator->total() }}</span>
                        resultados
                    </p>
                </div>

                <div>
                    <span class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white shadow-sm px-1 py-1">

                        {{-- Previous --}}
                        @if ($paginator->onFirstPage())
                            <span class="inline-flex items-center justify-center w-8 h-8 text-slate-300 cursor-not-allowed rounded-lg">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </span>
                        @else
                            <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="inline-flex items-center justify-center w-8 h-8 text-slate-500 rounded-lg hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150" aria-label="{{ __('pagination.previous') }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                        @endif

                        {{-- Pages --}}
                        @foreach ($elements as $element)
                            @if (is_string($element))
                                <span class="inline-flex items-center justify-center w-8 h-8 text-xs font-bold text-slate-400 cursor-default">{{ $element }}</span>
                            @endif

                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                        @if ($page == $paginator->currentPage())
                                            <span class="inline-flex items-center justify-center w-8 h-8 text-xs font-black text-white bg-slate-900 rounded-lg cursor-default">{{ $page }}</span>
                                        @else
                                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="inline-flex items-center justify-center w-8 h-8 text-xs font-bold text-slate-600 rounded-lg hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                                {{ $page }}
                                            </button>
                                        @endif
                                    </span>
                                @endforeach
                            @endif
                        @endforeach

                        {{-- Next --}}
                        @if ($paginator->hasMorePages())
                            <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="inline-flex items-center justify-center w-8 h-8 text-slate-500 rounded-lg hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150" aria-label="{{ __('pagination.next') }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        @else
                            <span class="inline-flex items-center justify-center w-8 h-8 text-slate-300 cursor-not-allowed rounded-lg">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        @endif

                    </span>
                </div>
            </div>
        </nav>
    @endif
</div>
