<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $seoDescription ?? 'Refacciones, componentes y soporte técnico para maquinaria agrícola e industrial.' }}">
    <title>{{ $title ?? 'Quantum Soluciones' }}</title>
    @if(isset($canonicalUrl))
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:title" content="{{ $title ?? 'Quantum Soluciones' }}">
        <meta property="og:description" content="{{ $seoDescription ?? 'Repuestos y soporte técnico de Quantum Soluciones.' }}">
        <meta property="og:type" content="{{ request()->routeIs('storefront.products.show') ? 'product' : 'website' }}">
        @isset($seoImage)<meta property="og:image" content="{{ $seoImage }}">@endisset
    @endif
    <link rel="icon" href="{{ asset('storefront/images/logo.webp') }}">
    @if(request()->routeIs('storefront.home'))
        <link rel="preload" as="image" href="{{ asset('storefront/images/machinery/hero-volvo-1600.webp') }}" type="image/webp">
    @endif
    @php
        $storefrontAssets = ['resources/css/storefront-public.css', 'resources/js/storefront-experience.js'];

        if (request()->routeIs('storefront.blog.show')) {
            array_unshift($storefrontAssets, 'resources/css/app.css');
        }

        $needsLivewire = request()->routeIs(
            'storefront.products.index',
            'storefront.products.category',
            'storefront.products.show',
            'storefront.services.index',
            'storefront.contact',
            'storefront.cart'
        );
    @endphp
    @vite($storefrontAssets)
    @if($needsLivewire)
        @livewireStyles
    @endif
    <link rel="stylesheet" href="{{ asset('storefront/fonts/poppins.css') }}">
</head>
<body class="storefront-compact commerce-catalog">
    @php
        $whatsappNumber = preg_replace('/\D+/', '', config('services.storefront.whatsapp_owner') ?: '50583262783');
        $whatsappUrl = 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode('¡Hola, Quantum! Quisiera cotizar una pieza.');
        $whatsappDisplay = preg_replace('/^(505)(\d{4})(\d{4})$/', '+$1 $2 $3', $whatsappNumber);
        $publicNav = [
            ['label' => 'Inicio', 'route' => 'storefront.home', 'active' => request()->routeIs('storefront.home')],
            ['label' => 'Productos', 'route' => 'storefront.products.index', 'active' => request()->routeIs('storefront.products.*')],
            ['label' => 'Servicios', 'route' => 'storefront.services.index', 'active' => request()->routeIs('storefront.services.*')],
            ['label' => 'Catálogos', 'route' => 'storefront.catalogs.index', 'active' => request()->routeIs('storefront.catalogs.*')],
            ['label' => 'Nosotros', 'route' => 'storefront.about', 'active' => request()->routeIs('storefront.about')],
            ['label' => 'Blog', 'route' => 'storefront.blog.index', 'active' => request()->routeIs('storefront.blog.*')],
            ['label' => 'Contacto', 'route' => 'storefront.contact', 'active' => request()->routeIs('storefront.contact')],
        ];
        $navCategories = app(\App\Services\Storefront\ProductService::class)->categoryOptions(6);
    @endphp

    <div class="storefront-fixed-shell">
        <div class="contact-strip">
            <div class="container">
                <span>Refacciones y soporte técnico para maquinaria agrícola e industrial</span>
                <div class="contact-strip-links">
                    <a href="mailto:info@quantumsoluciones.com"><i class="fas fa-envelope"></i> info@quantumsoluciones.com</a>
                    <a href="tel:+50583262783"><i class="fas fa-phone"></i> +505 8326 2783</a>
                </div>
            </div>
        </div>

        <header class="header-main-container">
            <div class="container header-wrapper">
                <div class="left-section">
                    <a class="logo" href="{{ route('storefront.home') }}" aria-label="Inicio Quantum Soluciones">
                        <img class="logo-mark" src="{{ asset('storefront/images/logo.webp') }}" alt="Quantum" width="52" height="36">
                        <img class="logo-wordmark" src="{{ asset('storefront/images/letras.webp') }}" alt="Quantum Soluciones" width="190" height="36">
                    </a>
                    <button class="menu-toggle" type="button" aria-label="Abrir menú" aria-controls="public-navigation-panel" aria-expanded="false"><i class="fas fa-bars"></i></button>
                </div>

                <nav class="desktop-main-nav" aria-label="Navegacion principal">
                    <a class="desktop-nav-link {{ request()->routeIs('storefront.home') ? 'is-active' : '' }}" href="{{ route('storefront.home') }}">Inicio</a>
                    <div class="desktop-nav-item {{ request()->routeIs('storefront.products.*') || request()->routeIs('storefront.modules.identify') ? 'is-active' : '' }}">
                        <a class="desktop-nav-link" href="{{ route('storefront.products.index') }}">Productos</a>
                        <div class="desktop-mega-menu">
                            <div>
                                <span>Cat&aacute;logo</span>
                                <a href="{{ route('storefront.products.index') }}">Todos los repuestos</a>
                                @foreach($navCategories->take(2) as $category)
                                    <a href="{{ \App\Support\StorefrontUrl::catalog($category['value']) }}">{{ $category['label'] }}</a>
                                @endforeach
                            </div>
                            <div>
                                <span>L&iacute;neas</span>
                                @foreach($navCategories->skip(2)->take(4) as $category)
                                    <a href="{{ \App\Support\StorefrontUrl::catalog($category['value']) }}">{{ $category['label'] }}</a>
                                @endforeach
                                <a href="{{ route('storefront.modules.identify') }}">Identificar pieza</a>
                            </div>
                        </div>
                    </div>
                    <div class="desktop-nav-item {{ request()->routeIs('storefront.services.*') || request()->routeIs('storefront.modules.maintenance', 'storefront.modules.diagnostics', 'storefront.modules.field-support') ? 'is-active' : '' }}">
                        <a class="desktop-nav-link" href="{{ route('storefront.services.index') }}">Servicios</a>
                        <div class="desktop-mega-menu desktop-mega-menu--compact">
                            <div>
                                <span>Soporte t&eacute;cnico</span>
                                <a href="{{ route('storefront.modules.maintenance') }}">Mantenimiento preventivo</a>
                                <a href="{{ route('storefront.modules.diagnostics') }}">Diagn&oacute;stico t&eacute;cnico</a>
                                <a href="{{ route('storefront.modules.field-support') }}">Soporte en campo</a>
                                <a href="{{ route('storefront.contact') }}">Solicitar visita</a>
                            </div>
                        </div>
                    </div>
                    <div class="desktop-nav-item {{ request()->routeIs('storefront.catalogs.*') || request()->routeIs('storefront.blog.*') || request()->routeIs('storefront.modules.guides', 'storefront.modules.technical-sheets') ? 'is-active' : '' }}">
                        <a class="desktop-nav-link" href="{{ route('storefront.catalogs.index') }}">Recursos</a>
                        <div class="desktop-mega-menu desktop-mega-menu--compact">
                            <div>
                                <span>Biblioteca</span>
                                <a href="{{ route('storefront.catalogs.index') }}">Cat&aacute;logos t&eacute;cnicos</a>
                                <a href="{{ route('storefront.blog.index') }}">Blog t&eacute;cnico</a>
                                <a href="{{ route('storefront.modules.technical-sheets') }}">Fichas y gu&iacute;as</a>
                                <a href="{{ route('storefront.legal.quotes') }}">Cotizaciones y garant&iacute;as</a>
                            </div>
                        </div>
                    </div>
                    <div class="desktop-nav-item {{ request()->routeIs('storefront.about') || request()->routeIs('storefront.legal.*') || request()->routeIs('storefront.modules.brands', 'storefront.modules.methodology') ? 'is-active' : '' }}">
                        <a class="desktop-nav-link" href="{{ route('storefront.about') }}">Empresa</a>
                        <div class="desktop-mega-menu desktop-mega-menu--compact">
                            <div>
                                <span>Quantum</span>
                                <a href="{{ route('storefront.about') }}">Nosotros</a>
                                <a href="{{ route('storefront.modules.brands') }}">Marcas que atendemos</a>
                                <a href="{{ route('storefront.legal.terms') }}">T&eacute;rminos y condiciones</a>
                                <a href="{{ route('storefront.legal.privacy') }}">Privacidad</a>
                            </div>
                        </div>
                    </div>
                    <a class="desktop-nav-link {{ request()->routeIs('storefront.contact') ? 'is-active' : '' }}" href="{{ route('storefront.contact') }}">Contacto</a>
                </nav>

                <div class="header-search">
                    <form class="header-search-form" action="{{ route('storefront.products.index') }}" method="get">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar número de parte, marca o modelo">
                        <button type="submit" aria-label="Buscar"><i class="fas fa-search"></i></button>
                    </form>
                </div>

                <div class="right-section">
                    @php
                        $accountUrl = route('login');
                        if (auth()->check()) {
                            $accountUrl = auth()->user()->hasRole(\App\Models\User::ROLE_CLIENTE_TIENDA)
                                ? route('customer.portal')
                                : route('dashboard');
                        }
                    @endphp
                    <a href="{{ $accountUrl }}" class="header-action" aria-label="Mi cuenta"><svg class="header-line-icon" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="16" cy="9" r="4.5"/><path d="M5 28c.6-7.1 4.4-11 11-11s10.4 3.9 11 11c-6.8 2.7-15.2 2.7-22 0Z"/></svg></a>
                    <a href="{{ $whatsappUrl }}" class="header-action header-quote" target="_blank" rel="noopener noreferrer" aria-label="Cotiza y compra por WhatsApp con Quantum"><img src="{{ asset('storefront/images/whatsapp-glyph-official.svg') }}" alt="" width="22" height="22"><span>Cotiza y compra</span></a>
                    <a href="{{ route('storefront.cart') }}" class="header-action storefront-cart-trigger {{ array_sum(session('storefront.cart', [])) > 0 ? 'has-items' : '' }}" aria-label="Carrito" @if(request()->routeIs('storefront.products.*')) x-data x-on:click.prevent="$dispatch('storefront-cart-open')" @endif>
                        <svg class="header-line-icon header-cart-icon" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4h4l4.2 18H26M7.5 9H30l-3.5 10H10"/><circle cx="12" cy="27" r="1.4"/><circle cx="25" cy="27" r="1.4"/></svg>
                        <span class="cart-count" style="display:flex">{{ array_sum(session('storefront.cart', [])) }}</span>
                    </a>
                </div>
            </div>
        </header>
    </div>

    @unless(request()->routeIs('storefront.home'))
        <section class="catalog-nav-section">
            <nav class="nav-bar" aria-label="Marcas y líneas de soporte">
                <div class="promo-ticker-shell">
                    <div class="promo-ticker">
                        <div class="promo-ticker-track">
                            <span>Volvo</span><span class="promo-separator">|</span>
                            <span>Komatsu</span><span class="promo-separator">|</span>
                            <span>Cummins</span><span class="promo-separator">|</span>
                            <span>Caterpillar</span><span class="promo-separator">|</span>
                            <span>Mack</span><span class="promo-separator">|</span>
                            <span>Sany</span><span class="promo-separator">|</span>
                            <span>Refacciones, equipos y suministros para minería, construcción y agricultura</span>
                        </div>
                    </div>
                </div>
            </nav>
        </section>
    @endunless

    <div class="mobile-menu-overlay" aria-hidden="true"></div>
    <aside id="public-navigation-panel" class="mobile-menu-panel" inert aria-hidden="true" aria-label="Menú de navegación">
        <div class="mobile-menu-head">
            <a class="mobile-menu-logo" href="{{ route('storefront.home') }}" aria-label="Inicio Quantum Soluciones">
                <img class="menu-brand-mark" src="{{ asset('storefront/images/logo.webp') }}" alt="" width="32" height="32" decoding="async"><img class="menu-brand-name" src="{{ asset('storefront/images/letras.webp') }}" alt="Quantum Soluciones" width="128" height="36" decoding="async">
            </a>
            <button class="mobile-menu-close" type="button" aria-label="Cerrar menú"><i class="fas fa-times"></i></button>
        </div>

        <div class="mobile-menu-body">
            <nav class="menu-primary-links" aria-label="Navegación principal">
                <a href="{{ route('storefront.home') }}" @if(request()->routeIs('storefront.home')) aria-current="page" @endif>Inicio<span aria-hidden="true">→</span></a>
                <a href="{{ route('storefront.products.index') }}" @if(request()->routeIs('storefront.products.*')) aria-current="page" @endif>Todos los repuestos<span aria-hidden="true">→</span></a>
                <details class="menu-link-group" @if(request()->route('category')) open @endif><summary>Explorar por categoría</summary><div>@foreach($navCategories as $category)<a href="{{ \App\Support\StorefrontUrl::catalog($category['value']) }}" @if(request()->route('category') === $category['value']) aria-current="page" @endif>{{ $category['label'] }}<small>{{ $category['products_count'] }}</small></a>@endforeach</div></details>
                <a href="{{ route('storefront.services.index') }}">Servicios técnicos<span aria-hidden="true">→</span></a>
                <details class="menu-link-group"><summary>Recursos técnicos</summary><div><a href="{{ route('storefront.catalogs.index') }}">Catálogos</a><a href="{{ route('storefront.blog.index') }}">Blog técnico</a><a href="{{ route('storefront.modules.technical-sheets') }}">Fichas y guías</a><a href="{{ route('storefront.modules.identify') }}">Identificar una pieza</a></div></details>
            </nav>
            <nav class="menu-primary-links menu-secondary-links" aria-label="Atención y empresa">@if(\Illuminate\Support\Facades\Route::has('public.tracking'))<a href="{{ route('public.tracking') }}">Seguimiento de pedido<span aria-hidden="true">→</span></a>@endif<a href="{{ route('storefront.contact') }}">Contacto y asesoría<span aria-hidden="true">→</span></a><a href="{{ route('storefront.about') }}">Sobre Quantum<span aria-hidden="true">→</span></a></nav>
            <details class="menu-link-group menu-legal-group"><summary>Información legal</summary><div><a href="{{ route('storefront.legal.terms') }}">Términos y condiciones</a><a href="{{ route('storefront.legal.privacy') }}">Privacidad</a><a href="{{ route('storefront.legal.quotes') }}">Cotizaciones y garantías</a><a href="{{ route('storefront.legal.cookies') }}">Cookies</a></div></details>
        </div>
        <div class="menu-contact-footer"><span>Atención directa</span><a href="tel:+50583262783">+505 8326 2783</a><small>Repuestos y soporte técnico</small></div>
    </aside>

    {{ $slot }}
    @if(request()->routeIs('storefront.products.*'))<livewire:storefront.cart-preview />@endif

    @unless(request()->routeIs('storefront.legal.*'))
    @include('storefront.partials.discover-promotion')
    @endunless
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col footer-brand">
                    <h4>Quantum Soluciones</h4>
                    <p>Especialistas en mantenimiento, refacciones y soporte técnico para maquinaria agrícola e industrial con enfoque en continuidad operativa.</p>
                    <ul class="footer-contact-list">
                        <li><i class="fas fa-location-dot"></i> Colonia Centro América, Managua</li>
                        <li><i class="fas fa-phone"></i> +505 8326 2783</li>
                        <li><i class="fas fa-envelope"></i> info@quantumsoluciones.com</li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Servicios</h4>
                    <ul>
                        <li><a href="{{ route('storefront.services.index') }}">Mantenimiento preventivo</a></li>
                        <li><a href="{{ route('storefront.services.index') }}">Diagnóstico técnico</a></li>
                        <li><a href="{{ route('storefront.products.index') }}">Venta de refacciones</a></li>
                        <li><a href="{{ route('storefront.contact') }}">Soporte postventa</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Información</h4>
                    <ul>
                        <li><a href="{{ route('storefront.about') }}">Nosotros</a></li>
                        <li><a href="{{ route('storefront.catalogs.index') }}">Catálogos</a></li>
                        <li><a href="{{ route('storefront.blog.index') }}">Blog técnico</a></li>
                        <li><a href="{{ route('storefront.contact') }}">Contacto</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Atención comercial</h4>
                    <p>Comparte el número de parte, modelo de máquina o una foto de la pieza. Validamos compatibilidad y disponibilidad.</p>
                    <a class="footer-cta" href="{{ route('storefront.contact') }}">Solicitar cotización</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; {{ date('Y') }} Quantum Soluciones. Todos los derechos reservados.</p>
                <nav class="footer-legal-links" aria-label="Enlaces legales">
                    <a href="{{ route('storefront.legal.terms') }}">T&eacute;rminos y condiciones</a>
                    <a href="{{ route('storefront.legal.privacy') }}">Pol&iacute;tica de privacidad</a>
                    <a href="{{ route('storefront.legal.quotes') }}">Cotizaciones y garant&iacute;as</a>
                    <a href="{{ route('storefront.legal.cookies') }}">Cookies</a>
                    <button type="button" data-cookie-open>Preferencias de cookies</button>
                </nav>
            </div>
        </div>
    </footer>

    <div class="whatsapp-bubble" data-whatsapp-bubble hidden><a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" data-whatsapp-message>¿Encontraste lo que buscabas?</a><button type="button" data-whatsapp-close aria-label="Cerrar mensaje"><svg viewBox="0 0 12 12" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m2 2 8 8M10 2l-8 8" stroke-linecap="round"/></svg></button></div>
    <a class="whatsapp-float" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Contactar a Quantum por WhatsApp">
        <img src="{{ asset('storefront/images/whatsapp-glyph-official.svg') }}" alt="" width="32" height="32" loading="lazy">
    </a>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const open = document.querySelector('.menu-toggle');
            const close = document.querySelector('.mobile-menu-close');
            const overlay = document.querySelector('.mobile-menu-overlay');
            const panel = document.querySelector('.mobile-menu-panel');
            const toggle = state => {
                document.body.classList.toggle('menu-open', state);
                panel?.classList.toggle('is-open', state);
                overlay?.classList.toggle('is-open', state);
                panel?.setAttribute('aria-hidden', state ? 'false' : 'true');
                if (panel) panel.inert = !state;
                open?.setAttribute('aria-expanded', String(state));
                if (state) close?.focus(); else open?.focus();
                overlay?.setAttribute('aria-hidden', state ? 'false' : 'true');
            };
            open?.addEventListener('click', () => toggle(true));
            close?.addEventListener('click', () => toggle(false));
            overlay?.addEventListener('click', () => toggle(false));
            document.addEventListener('keydown', event => {
                if (!document.body.classList.contains('menu-open')) return;
                if (event.key === 'Escape') { event.preventDefault(); toggle(false); }
                if (event.key === 'Tab') {
                    const controls = [...panel.querySelectorAll('a[href],button,summary')].filter(el => el.getClientRects().length);
                    const first = controls[0], last = controls[controls.length - 1];
                    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
                    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
                }
            });
            document.addEventListener('click', event => {
                if (event.target.closest('[data-products-filter-open]')) {
                    document.body.classList.add('products-filter-open');
                }

                if (event.target.closest('[data-products-filter-close]')) {
                    document.body.classList.remove('products-filter-open');
                }
            });
            const updateCartIndicator = event => {
                const count = Number(event.detail.count) || 0;
                document.querySelectorAll('.cart-count').forEach(el => el.textContent = count);
                document.querySelectorAll('.storefront-cart-trigger').forEach(el => {
                    el.classList.toggle('has-items', count > 0);
                    el.classList.remove('cart-updated');
                    if (count > 0) { void el.offsetWidth; el.classList.add('cart-updated'); }
                });
            };
            window.addEventListener('storefront-cart-added', updateCartIndicator);
            window.addEventListener('storefront-cart-count', updateCartIndicator);
            const syncHeader = () => document.body.classList.toggle('header-scrolled', window.scrollY > 80);
            window.addEventListener('scroll', syncHeader, { passive: true });
            syncHeader();
            document.querySelectorAll('.reveal-up').forEach(element => element.classList.add('is-visible'));
        });
    </script>
    @include('storefront.partials.cookie-preferences')
    @if($needsLivewire)
        @livewireScripts
    @endif
</body>
</html>
