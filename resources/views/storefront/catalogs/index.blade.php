<x-layouts.storefront>
    <x-slot:title>Catálogos - Quantum Soluciones</x-slot:title>
    <main class="catalog-page">
        <div class="container">
            <section class="commerce-page-hero catalog-hero-shell">
                <span class="commerce-kicker">Biblioteca técnica</span>
                <div class="catalog-hero">
                    <h1>Catálogos, fichas y documentos de referencia</h1>
                    <p>Consulta información técnica para validar compatibilidad, revisar especificaciones y preparar solicitudes de repuestos con mayor precisión.</p>
                </div>
                <a class="commerce-hero-cta" href="{{ route('storefront.contact') }}">Solicitar catálogo</a>
            </section>

            <section class="resource-type-grid">
                <article><strong>Fichas técnicas</strong><span>Datos clave de componentes y aplicaciones.</span></article>
                <article><strong>Catálogos PDF</strong><span>Referencias para compras y mantenimiento.</span></article>
                <article><strong>Compatibilidades</strong><span>Validación por marca, modelo y número de parte.</span></article>
                <article><strong>Manuales y guías</strong><span>Soporte para diagnóstico y operación.</span></article>
            </section>

            @if($catalogs->isEmpty())
                <section class="storefront-empty-state catalog-empty">
                    <span class="empty-eyebrow">Recursos en preparación</span>
                    <h2>Estamos preparando catálogos técnicos y fichas de referencia.</h2>
                    <p>Si necesitas información sobre una pieza específica, podemos ayudarte directamente con compatibilidad, alternativas y disponibilidad.</p>
                    <div class="empty-actions">
                        <a class="btn-primary" href="{{ route('storefront.contact') }}">Solicitar catálogo</a>
                        <a class="btn-secondary" href="{{ route('storefront.contact') }}">Consultar compatibilidad</a>
                        <a class="btn-secondary" href="{{ route('storefront.products.index') }}">Ver productos</a>
                    </div>
                </section>
            @else
                <section class="catalog-grid">
                    @foreach($catalogs as $catalog)
                        <article class="catalog-card">
                            <div class="catalog-card-head">
                                <span class="catalog-type">{{ strtoupper(pathinfo($catalog->archivo, PATHINFO_EXTENSION) ?: 'DOC') }}</span>
                                @if($catalog->categoria)<span class="catalog-brand">{{ $catalog->categoria }}</span>@endif
                            </div>
                            <h3>{{ $catalog->titulo ?: $catalog->nombre }}</h3>
                            <p class="catalog-context">Documento técnico/comercial disponible en la web de Quantum Soluciones.</p>
                            <p>{{ $catalog->descripcion ?: 'Documento descargable de soporte técnico y comercial.' }}</p>
                            <div class="catalog-meta"><span><i class="far fa-calendar"></i> {{ $catalog->created_at ? \Carbon\Carbon::parse($catalog->created_at)->format('d/m/Y') : 'Disponible' }}</span></div>
                            <a class="catalog-download-btn" href="{{ route('storefront.catalogs.download', ['file' => $catalog->archivo]) }}"><i class="fas fa-download"></i> Descargar</a>
                        </article>
                    @endforeach
                </section>
            @endif
        </div>
    </main>
</x-layouts.storefront>
