<div>
    <dialog class="commerce-cart-drawer" x-ref="panel" wire:ignore.self x-data="{ closeDrawer() { const dialog = this.$refs.panel; if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { dialog.close(); return; } dialog.classList.add('is-closing'); setTimeout(() => { dialog.close(); dialog.classList.remove('is-closing'); }, 160); } }"
        x-on:cancel.prevent="closeDrawer()"
        x-on:storefront-cart-added.window="if (!$el.open) $el.showModal()"
        x-on:storefront-cart-open.window="if (!$el.open) $el.showModal()"
        x-on:click="if ($event.target === $el) closeDrawer()" aria-labelledby="cart-preview-title">
        <header class="cart-drawer-heading"><div><p class="commerce-kicker">Tu selección</p><h2 id="cart-preview-title">Carrito de cotización <span>({{ $count }})</span></h2></div><button type="button" x-on:click="closeDrawer()" aria-label="Cerrar carrito">×</button></header>
        <div class="cart-drawer-items">
            @if($items->isNotEmpty())
                <section class="cart-shipping-goal" aria-label="Progreso para envío gratis">
                    <div class="cart-shipping-title"><strong>Envío gratis</strong><span>A partir de US${{ number_format($summary['thresholdUsd'], 2) }}</span></div>
                    @if($summary['thresholdNio'] !== null)<p>≈ C${{ number_format($summary['thresholdNio'], 0) }} · Tasa vigente del sistema</p>@endif
                    <progress max="100" value="{{ $summary['progress'] }}" aria-label="Progreso para envío gratis">{{ round($summary['progress']) }}%</progress>
                    <p role="status">@if($summary['reached'])Tu solicitud califica para envío gratis.@elseif($summary['remainingUsd'] !== null)Agrega <strong>US${{ number_format($summary['remainingUsd'], 2) }}</strong>@if($summary['remainingNio'] !== null) (≈ C${{ number_format($summary['remainingNio'], 0) }})@endif más para obtener envío gratis.@else Confirmaremos el progreso cuando estén disponibles los precios y la tasa de cambio.@endif</p>
                </section>
            @endif
            @forelse($items as $item)
                @php($line = $summary['lines'][$item->id])
                <article class="cart-drawer-item" wire:key="preview-{{ $item->id }}">
                    <a href="{{ \App\Support\StorefrontUrl::product($item) }}"><img src="{{ \App\Support\StorefrontUrl::productImage($item->image_path) }}" alt="{{ $item->name }}"></a>
                    <div>@unless($item->image_path)<span class="product-reference-label">Imagen de referencia</span>@endunless<p class="products-sku">{{ $item->part_number ?: $item->sku }}</p><h3><a href="{{ \App\Support\StorefrontUrl::product($item) }}">{{ $item->name }}</a></h3>
                        @if($item->brand || $item->technical_category)<p class="cart-item-meta">{{ $item->brand }}{{ $item->brand && $item->technical_category ? ' · ' : '' }}{{ $item->technical_category?->label() }}</p>@endif
                        <p class="products-stock-pill {{ $item->stock_on_hand > 0 ? 'is-in' : 'is-out' }}">{{ $item->stock_on_hand > 0 ? 'En stock' : 'Bajo pedido' }}</p>
                        @if($item->cart_quantity > $item->stock_on_hand && $item->stock_on_hand > 0)<p class="cart-stock-notice">Hay {{ $item->stock_on_hand }} unidades en inventario. Confirmaremos el plazo para las restantes.</p>@endif
                        @if($line['unitUsd'] !== null)<div class="cart-line-price"><span>US${{ number_format($line['unitUsd'], 2) }} c/u</span><strong>US${{ number_format($line['totalUsd'], 2) }}</strong></div>@else<p class="cart-item-meta">Precio por confirmar</p>@endif
                        <div class="cart-drawer-controls"><div class="commerce-quantity"><button type="button" wire:click="changeQuantity({{ $item->id }}, -1)" wire:loading.attr="disabled" aria-label="Reducir cantidad de {{ $item->name }}" @disabled($item->cart_quantity <= 1)>−</button><output aria-label="Cantidad de {{ $item->name }}">{{ $item->cart_quantity }}</output><button type="button" wire:click="changeQuantity({{ $item->id }}, 1)" wire:loading.attr="disabled" aria-label="Aumentar cantidad de {{ $item->name }}" @disabled($item->cart_quantity >= 999)>+</button></div><button type="button" wire:click="remove({{ $item->id }})" wire:loading.attr="disabled" class="cart-remove-line">Eliminar</button></div>
                    </div>
                </article>
            @empty
                <div class="cart-drawer-empty"><h3>Tu carrito de cotización está vacío.</h3><p>Explora nuestro catálogo y agrega los repuestos que necesitas.</p><a class="products-add-btn" href="{{ route('storefront.products.index') }}">Explorar productos</a></div>
            @endforelse

        </div>
        <footer class="cart-drawer-footer">
            @if($items->isNotEmpty())
                <div class="cart-preview-subtotal"><span>Subtotal referencial</span><div><strong>{{ $summary['usd'] !== null ? 'US$'.number_format($summary['usd'], 2) : 'Por confirmar' }}</strong>@if($summary['nio'] !== null)<small>≈ C${{ number_format($summary['nio'], 2) }}</small>@endif</div></div>
                <p>Confirmamos precio, compatibilidad y entrega antes de realizar el cobro.</p>
                <a class="products-add-btn" href="{{ route('storefront.cart') }}">Revisar y solicitar cotización →</a>
            @endif
            <button type="button" x-on:click="closeDrawer()" class="products-detail-btn">Seguir explorando</button>
            @if($items->isNotEmpty())<details class="cart-quote-benefits"><summary>¿Por qué cotizar y comprar con Quantum?</summary><ul><li><strong>Asesoría técnica</strong><span>Te ayudamos a identificar la refacción.</span></li><li><strong>Compatibilidad verificada</strong><span>Revisamos la aplicación y disponibilidad.</span></li><li><strong>Atención directa</strong><span>Seguimiento durante tu cotización.</span></li></ul></details>@endif
            <div class="cart-drawer-brand" style="align-items:center;gap:8px"><img src="{{ asset('storefront/images/logo.webp') }}" alt="" width="32" height="32" style="width:32px;height:32px"><img src="{{ asset('storefront/images/letras.webp') }}" alt="Quantum Soluciones" width="112" height="32" style="width:112px;height:32px"></div>
        </footer>
    </dialog>
</div>
