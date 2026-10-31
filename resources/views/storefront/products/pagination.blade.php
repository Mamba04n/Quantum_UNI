@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación de productos" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px;font-size:12px;color:#64748b">
        <p style="margin:0;font-weight:600">
            Mostrando <strong style="color:#172033">{{ $paginator->firstItem() }}</strong> a <strong style="color:#172033">{{ $paginator->lastItem() }}</strong> de <strong style="color:#172033">{{ $paginator->total() }}</strong> resultados
        </p>
        <div style="display:flex;align-items:center;gap:4px">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #e2e8f0;border-radius:7px;color:#cbd5e1;cursor:not-allowed">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #e2e8f0;border-radius:7px;color:#334155;text-decoration:none">‹</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:32px;color:#94a3b8">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border-radius:7px;background:#172033;color:#fff;font-weight:800">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Ir a página {{ $page }}" style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border-radius:7px;color:#475569;text-decoration:none">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Página siguiente" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #e2e8f0;border-radius:7px;color:#334155;text-decoration:none">›</a>
            @else
                <span aria-disabled="true" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #e2e8f0;border-radius:7px;color:#cbd5e1;cursor:not-allowed">›</span>
            @endif
        </div>
    </nav>
@endif
