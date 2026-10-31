<x-layouts.storefront>
    <x-slot:title>Blog técnico - Quantum Soluciones</x-slot:title>
    <main class="tech-blog-page">
        <div class="container">
            <section class="commerce-page-hero tech-blog-hero">
                <span class="commerce-kicker">Contenido técnico</span>
                <div>
                    <h1>Blog técnico para mantenimiento y repuestos</h1>
                    <p>Guías, compatibilidades, criterios de compra y recomendaciones para maquinaria agrícola e industrial.</p>
                </div>
                <a class="commerce-hero-cta" href="{{ route('storefront.contact') }}">Consultar caso</a>
            </section>

            <section class="tech-blog-layout">
                <aside class="tech-blog-sidebar">
                    <div class="tech-blog-sidebar-head"><h3>Categorías</h3></div>
                    <ul>
                        <li>
                            <a class="{{ empty($activeCategory) ? 'is-active' : '' }}" href="{{ route('storefront.blog.index') }}">
                                Todas <span>{{ $categories->sum('total') ?: $posts->count() }}</span>
                            </a>
                        </li>
                        @foreach($categories as $category)
                            <li>
                                <a class="{{ $activeCategory === $category->categoria ? 'is-active' : '' }}" href="{{ route('storefront.blog.index', ['categoria' => $category->categoria]) }}">
                                    {{ $category->categoria }} <span>{{ $category->total }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="blog-topic-list">
                        <strong>Temas próximos</strong>
                        <span>Mantenimiento preventivo</span>
                        <span>Identificación de repuestos</span>
                        <span>Compatibilidad por modelo</span>
                        <span>Diagnóstico de fallas comunes</span>
                    </div>
                </aside>
                <div class="tech-blog-content">
                    @if($posts->isEmpty())
                        <section class="storefront-empty-state tech-blog-empty">
                            <span class="empty-eyebrow">Editorial técnico</span>
                            <h2>Pronto publicaremos guías técnicas para tu operación.</h2>
                            <p>Estamos preparando contenido sobre compatibilidad de piezas, mantenimiento preventivo, identificación de repuestos y recomendaciones para maquinaria agrícola.</p>
                            <div class="empty-actions">
                                <a class="btn-primary" href="{{ route('storefront.contact') }}">Solicitar asistencia técnica</a>
                                <a class="btn-secondary" href="{{ route('storefront.services.index') }}">Ver servicios</a>
                            </div>
                        </section>
                    @else
                        <section class="tech-blog-grid">
                            @foreach($posts as $post)
                                <article class="tech-blog-card">
                                    <a class="tech-blog-thumb" href="{{ route('storefront.blog.show', $post->slug) }}"><img src="{{ \App\Support\StorefrontUrl::blogImage($post->imagen) }}" alt="{{ $post->titulo }}" onerror="this.src='{{ asset('storefront/images/product-placeholder.svg') }}'"></a>
                                    <div class="tech-blog-body">
                                        <p class="tech-blog-machine">{{ $post->categoria ?: $post->maquina }}</p>
                                        <h2><a href="{{ route('storefront.blog.show', $post->slug) }}">{{ $post->titulo }}</a></h2>
                                        @if($post->numero_parte)<p class="tech-blog-part">N° parte: {{ $post->numero_parte }}</p>@endif
                                        <p class="tech-blog-summary">{{ $post->resumen ?: \Illuminate\Support\Str::limit(strip_tags($post->contenido), 210) }}</p>
                                        <div class="tech-blog-meta"><span>{{ \Carbon\Carbon::parse($post->created_at)->format('d/m/Y') }}</span><a href="{{ route('storefront.blog.show', $post->slug) }}">Leer más</a></div>
                                    </div>
                                </article>
                            @endforeach
                        </section>
                    @endif
                    <section class="tech-blog-cta"><h3>¿No encuentras una compatibilidad específica?</h3><p>Escríbenos con el modelo de máquina y número de parte. Validamos referencias antes de cotizar.</p><a href="{{ route('storefront.contact') }}">Solicitar asistencia técnica</a></section>
                </div>
            </section>
        </div>
    </main>
</x-layouts.storefront>
