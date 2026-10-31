<main class="products-page">
    <div class="container">
        <div class="catalog-shipping-banner">
            <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v11H6m8-8h4l3 4v4h-2M2 10h6M1 13h5M8 17h7"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>
            <strong>ENVÍO GRATIS</strong>
            <span>En cotizaciones desde US${{ number_format((float) config('services.storefront.free_shipping_threshold_usd', '250.00'), 0) }}</span>
            <a href="{{ route('storefront.contact') }}">Consultar condiciones</a>
        </div>
        <nav class="catalog-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('storefront.home') }}">Inicio</a><span aria-hidden="true">/</span>@if($categoria)<button type="button" wire:click="$set('categoria', '')">Repuestos</button><span aria-hidden="true">/</span><span aria-current="page">{{ $categories->firstWhere('value', $categoria)['label'] ?? $categoria }}</span>@else<span aria-current="page">Repuestos y componentes</span>@endif</nav>
        <header class="commerce-catalog-heading catalog-heading-compact">
            <div><h1>{{ $categoria ? ($categories->firstWhere('value', $categoria)['label'] ?? 'Repuestos y componentes') : 'Repuestos y componentes' }}</h1><p>Busca por número de parte, marca o descripción. Revisa disponibilidad y solicita tu cotización.</p></div>
            <a class="catalog-advisor-link" href="{{ route('storefront.contact') }}">Ayuda para identificar una pieza &rarr;</a>
        </header>

        @if(session('success'))
            <div class="contact-status success" style="margin-bottom:16px">{{ session('success') }}</div>
        @endif

        <div class="products-mobile-filter-bar">
            <button type="button" class="products-filter-toggle" data-products-filter-open>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M6 12h12M9 18h6"/><circle cx="8" cy="6" r="2" fill="white"/><circle cx="16" cy="12" r="2" fill="white"/></svg>
                <span>Filtros</span>
            </button>
            <label class="catalog-mobile-sort"><span>Ordenar productos</span><select aria-label="Ordenar productos en móvil" wire:model.live="sort"><option value="name">Nombre: A a Z</option><option value="name_desc">Nombre: Z a A</option><option value="newest">Más recientes</option></select></label>
            <span>{{ $products->total() }} producto(s)</span>
        </div>

        <div class="products-filter-overlay" data-products-filter-close aria-hidden="true"></div>

        <div class="products-layout">
            <aside class="products-sidebar">
                <div class="products-sidebar-head">
                    <div>
                        <h2>Filtros</h2>
                    </div>
                    <button type="button" wire:click="clearFilters" class="catalog-filter-clear">Limpiar</button>
                    <button type="button" class="products-filter-close" data-products-filter-close aria-label="Cerrar filtros">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m5 5 14 14M19 5 5 19"/></svg>
                    </button>
                </div>
                <div class="products-filter-block">
                    <label class="products-filter-title" for="product-search">Buscar repuesto</label>
                    <input id="product-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Número de parte o nombre" class="products-sort-select">
                </div>
                <fieldset class="catalog-facet"><legend>Disponibilidad</legend><label class="catalog-facet-option"><input type="checkbox" wire:model.live="inStock"><span>Solo productos en stock</span></label></fieldset>
                <fieldset class="catalog-facet"><legend>Categorías</legend><label class="catalog-facet-option"><input type="radio" wire:model.live="categoria" value=""><span>Todas las categorías</span></label>@foreach($categories as $option)<label class="catalog-facet-option"><input type="radio" wire:model.live="categoria" value="{{ $option['value'] }}"><span>{{ $option['label'] }}</span><small>{{ $option['products_count'] }}</small></label>@endforeach</fieldset>
                <fieldset class="catalog-facet"><legend>Marcas</legend><div class="catalog-facet-scroll"><label class="catalog-facet-option"><input type="radio" wire:model.live="brand" value=""><span>Todas las marcas</span></label>@foreach($brandCounts as $brandName => $brandCount)<label class="catalog-facet-option"><input type="radio" wire:model.live="brand" value="{{ $brandName }}"><span>{{ $brandName }}</span><small>{{ $brandCount }}</small></label>@endforeach</div><p class="catalog-facet-hint">Opciones y cantidades según los filtros actuales.</p></fieldset>
                <div class="products-filter-actions">
                    <button type="button" class="products-filter-apply" data-products-filter-close>Ver resultados</button>
                </div>
                <div class="products-sidebar-note">
                    <strong>¿No sabes el número de parte?</strong>
                    <p>Envíanos una foto de la pieza, placa o modelo del equipo. Validamos compatibilidad antes de cotizar.</p>
                </div>
            </aside>

            <section class="products-results" wire:loading.class="is-loading">
                <div class="products-results-head">
                    <p class="products-results-count">{{ $products->firstItem() ?? 0 }}&ndash;{{ $products->lastItem() ?? 0 }} de {{ $products->total() }} productos</p>
                    <label class="catalog-sort">Ordenar por<select aria-label="Ordenar productos" wire:model.live="sort" class="products-sort-select"><option value="name">Nombre: A a Z</option><option value="name_desc">Nombre: Z a A</option><option value="newest">Más recientes</option></select></label>
                </div>
                @if($search || $brand || $categoria || $inStock || $type)
                <div class="catalog-active-filters" aria-label="Filtros activos"><span>Filtrado por:</span>@if($search)<button type="button" wire:click="$set('search', '')">{{ $search }} ×</button>@endif @if($brand)<button type="button" wire:click="$set('brand', '')">{{ $brand }} ×</button>@endif @if($categoria)<button type="button" wire:click="$set('categoria', '')">{{ $categories->firstWhere('value', $categoria)['label'] ?? $categoria }} ×</button>@endif @if($inStock)<button type="button" wire:click="$set('inStock', false)">En stock ×</button>@endif<button type="button" wire:click="clearFilters">Limpiar todos</button></div>
                @endif
                <div class="products-cards-grid {{ $products->count() >= 4 ? 'is-balanced' : 'is-compact' }}">
                    @forelse($products as $product)
                        <article class="products-item-card" wire:key="catalog-product-{{ $product->id }}">
                            <a class="products-item-link" href="{{ \App\Support\StorefrontUrl::product($product) }}">
                                <div class="products-item-image">
                                    <img src="{{ \App\Support\StorefrontUrl::productImage($product->image_path) }}" alt="{{ $product->name }}" loading="lazy" onerror="this.src='{{ asset('storefront/images/product-placeholder.svg') }}'">
                                </div>
                            </a>
                            <div class="products-item-body">
                                @unless($product->image_path)<span class="product-reference-label">Imagen de referencia</span>@endunless
                                <h3><a href="{{ \App\Support\StorefrontUrl::product($product) }}">{{ $product->name }}</a></h3>
                                @if($product->part_number)<p class="catalog-part">Parte: {{ $product->part_number }}</p>@endif
                                <p class="products-meta-line">{{ trim(($product->brand ?: '').' · '.($product->technical_category?->label() ?? 'Sin categoría'), ' ·') }}</p>
                                <p class="catalog-price-note">{{ \App\Support\StorefrontPrice::label($product) }}</p>
                                <p class="products-stock-pill {{ $product->stock_on_hand > 0 ? 'is-in' : 'is-out' }}">{{ $product->stock_on_hand > 0 ? 'En stock' : 'Consultar disponibilidad' }}</p>
                                <div class="products-card-actions">
                                    <button type="button" wire:click="add({{ $product->id }})" wire:loading.attr="disabled" wire:target="add({{ $product->id }})" class="products-add-btn"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h3l3 14h12M6 7h16l-3 7H8"/><circle cx="9" cy="21" r="1"/><circle cx="19" cy="21" r="1"/></svg> Agregar</button>
                                    <a class="products-detail-btn" href="{{ \App\Support\StorefrontUrl::product($product) }}">Ver más</a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <article class="storefront-empty-state products-empty-state">
                            <span class="empty-eyebrow">Búsqueda técnica</span>
                            <h2>No encontramos productos con estos filtros.</h2>
                            <p>Puedes buscar por nombre, número de parte, marca o modelo de máquina. También podemos ayudarte a identificar la pieza correcta.</p>
                            <div class="empty-actions">
                                <a class="btn-primary" href="{{ route('storefront.contact') }}">Solicitar asistencia</a>
                                <button type="button" wire:click="clearFilters" class="btn-secondary">Limpiar filtros</button>
                                <a class="btn-secondary" href="{{ route('storefront.services.index') }}">Ver servicios técnicos</a>
                            </div>
                        </article>
                    @endforelse
                </div>
                <div style="margin-top:28px">{{ $products->links('storefront.products.pagination') }}</div>
            </section>
        </div>

        <section class="commerce-support-band">
            <div>
                <span>Identificación de repuestos</span>
                <h2>¿No tienes el número de parte?</h2>
                <p>Comparte una fotografía de la pieza, placa del equipo o modelo de máquina. Te ayudamos a confirmar compatibilidad antes de comprar.</p>
            </div>
            <a href="{{ route('storefront.contact') }}">Enviar requerimiento</a>
        </section>
    </div>
</main>
