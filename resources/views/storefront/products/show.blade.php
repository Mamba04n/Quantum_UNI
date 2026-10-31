<x-layouts.storefront>
    <x-slot:title>{{ $seoTitle }}</x-slot:title>
    <x-slot:canonicalUrl>{{ $canonicalUrl }}</x-slot:canonicalUrl>
    <x-slot:seoDescription>{{ $seoDescription }}</x-slot:seoDescription>
    <x-slot:seoImage>{{ \App\Support\StorefrontUrl::productImage($product->image_path, asset('storefront/images/product-unavailable.svg')) }}</x-slot:seoImage>
    <main class="product-detail-page">
        <div class="container product-detail-container">
            <nav class="detail-breadcrumb" aria-label="Ruta de navegación del producto">
                <a href="{{ route('storefront.home') }}">Inicio</a><span>/</span>
                <a href="{{ route('storefront.products.index') }}">Productos</a><span>/</span>
                @if($product->technical_category)<a href="{{ \App\Support\StorefrontUrl::catalog($product->technical_category->value) }}">{{ $product->technical_category->label() }}</a><span>/</span>@endif
                <strong aria-current="page">{{ $product->name }}</strong>
            </nav>
            <section class="detail-shell">
                <article class="detail-gallery-card" x-data="{ image: @js(\App\Support\StorefrontUrl::productImage($product->image_path, asset('storefront/images/product-unavailable.svg'))) }">
                    <div class="detail-main-image"><img :src="image" src="{{ \App\Support\StorefrontUrl::productImage($product->image_path, asset('storefront/images/product-unavailable.svg')) }}" alt="{{ $product->name }}" onerror="this.src='{{ asset('storefront/images/product-placeholder.svg') }}'"></div>
                    @unless($product->image_path)<p class="product-reference-label">Fotografía del producto no disponible.</p>@endunless
                    @if($product->images->isNotEmpty())<div class="detail-thumbnails"><button type="button" x-on:click="image = @js(\App\Support\StorefrontUrl::productImage($product->image_path, asset('storefront/images/product-unavailable.svg')))" aria-label="Ver imagen principal"><img src="{{ \App\Support\StorefrontUrl::productImage($product->image_path, asset('storefront/images/product-unavailable.svg')) }}" alt="Vista principal"></button>@foreach($product->images as $photo)<button type="button" x-on:click="image = @js(\App\Support\StorefrontUrl::productImage($photo->image_path))" aria-label="Ver imagen {{ $loop->iteration + 1 }}"><img src="{{ \App\Support\StorefrontUrl::productImage($photo->image_path) }}" alt="{{ $photo->alt_text ?: $product->name }}"></button>@endforeach</div>@endif
                </article>
                <article class="detail-info-card">
                    @if($product->brand)<a class="detail-brand-link" href="{{ \App\Support\StorefrontUrl::catalog(null, ['marca' => $product->brand]) }}">{{ $product->brand }}</a>@endif
                    <h1>{{ $product->name }}</h1>
                    <div class="detail-meta">
                        @if($product->part_number)<span class="detail-part-number">N° Parte: <b>{{ $product->part_number }}</b></span>@endif
                    </div>
                    <div class="detail-stock {{ $product->stock_on_hand > 0 ? 'is-in' : 'is-out' }}">
                        <i class="fas {{ $product->stock_on_hand > 0 ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i>
                        {{ $product->stock_on_hand > 0 ? 'Disponible ('.$product->stock_on_hand.' en stock)' : 'Sin stock por el momento' }}
                    </div>
                    <div class="detail-specs">
                        <span><b>Marca:</b> {{ $product->brand ?: 'N/D' }}</span>
                        <span><b>Categoría:</b> {{ $product->technical_category?->label() ?: 'Por confirmar' }}</span>
                    </div>
                    <div class="detail-quote-summary"><span>Precio unitario</span><strong>{{ \App\Support\StorefrontPrice::label($product) }}</strong><p>Precio y entrega sujetos a confirmación al cotizar.</p></div>
                    <form action="{{ route('storefront.products.cart.add', $product) }}" method="POST" class="detail-actions">
                        @csrf
                        <label class="detail-quantity-label">Cantidad<input type="number" name="quantity" aria-label="Cantidad" class="detail-qty-input" value="1" min="1" max="999"></label>
                        <button type="submit" class="products-add-btn add-to-cart-large"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 3h2l3 12h11l2-8H6M10 20h.01M18 20h.01" stroke-linecap="round" stroke-linejoin="round"/></svg> Agregar a mi cotización</button>
                    </form>
                    <div class="detail-assurance-grid"><div><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3 4 6v6c0 4 8 9 8 9s8-5 8-9V6l-8-3Z"/><path d="m8 12 3 3 5-6"/></svg><strong>Compatibilidad</strong><span>Validación con tu equipo</span></div><div><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h11v11H3zm11 4h4l3 4v3h-7"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg><strong>Entrega coordinada</strong><span>Plazo confirmado al cotizar</span></div><div><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 14v-2a8 8 0 0 1 16 0v2M4 12H2v7h4v-7zm16 0h2v7h-4v-7z"/></svg><strong>Atención técnica</strong><span>Asesoría para tu selección</span></div></div>
                    <a class="detail-advisor" href="{{ route('storefront.contact', ['product' => $product->part_number ?: $product->sku]) }}">¿Tienes dudas sobre esta pieza? Consulta con un asesor &rarr;</a>
                </article>
            </section>
            <section class="detail-technical-sections"><details open><summary>Descripción del producto</summary><p>{{ $product->description ?: 'Consulta especificaciones, compatibilidad y tiempo de entrega con nuestro equipo técnico.' }}</p></details><details open><summary>Identificación y especificaciones</summary><dl><div><dt>Número de parte</dt><dd>{{ $product->part_number ?: 'Por confirmar' }}</dd></div><div><dt>Marca</dt><dd>{{ $product->brand ?: 'Por confirmar' }}</dd></div><div><dt>Categoría</dt><dd>{{ $product->technical_category?->label() ?: 'Por confirmar' }}</dd></div></dl></details><details><summary>Compatibilidad y entrega</summary><p>Comparte el modelo y número de serie de tu equipo. Validamos la aplicación de la pieza y confirmamos las condiciones de entrega en la cotización.</p><a href="{{ route('storefront.contact') }}">Consultar con un asesor &rarr;</a></details></section>
            @if($relatedProducts->isNotEmpty())
                <section class="detail-related-block">
                    <div class="section-header"><h3>Productos relacionados</h3><a href="{{ route('storefront.products.index') }}">Ver catálogo <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;margin-left:4px"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a></div>
                    <div class="detail-related-grid">
                        @foreach($relatedProducts as $item)
                            <article class="detail-related-card">
                                <a class="detail-related-image" href="{{ \App\Support\StorefrontUrl::product($item) }}"><img src="{{ \App\Support\StorefrontUrl::productImage($item->image_path, asset('storefront/images/product-unavailable.svg')) }}" alt="{{ $item->name }}"></a>
                                <div class="detail-related-body"><h4><a href="{{ \App\Support\StorefrontUrl::product($item) }}">{{ $item->name }}</a></h4><p class="products-sku">Parte: {{ $item->part_number ?: 'Por confirmar' }}</p><p class="products-meta-line">{{ $item->brand }}</p><strong class="detail-related-price">{{ \App\Support\StorefrontPrice::label($item) }}</strong><a class="products-detail-btn" href="{{ \App\Support\StorefrontUrl::product($item) }}">Ver pieza &rarr;</a></div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </main>
</x-layouts.storefront>
