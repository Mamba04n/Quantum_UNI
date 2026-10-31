<x-layouts.storefront>
    <x-slot:title>Quantum Soluciones | Refacciones y soporte técnico</x-slot:title>

    <main id="sections-root" class="home-commerce">
        <section class="qs-dynamic-hero" data-qs-hero>
            <div class="qs-dynamic-hero__slides" aria-hidden="true">
                <img class="is-active" src="{{ asset('storefront/images/machinery/hero-volvo-1600.webp') }}" alt="" width="1600" height="1011" fetchpriority="high">
                <img data-src="{{ asset('storefront/images/machinery/hero-komatsu-1600.webp') }}" alt="" width="1600" height="814">
                <img data-src="{{ asset('storefront/images/machinery/hero-caterpillar-1600.webp') }}" alt="" width="1600" height="1071">
            </div>
            <div class="container qs-dynamic-hero__inner">
                <div class="qs-dynamic-copy">
                    <span class="qs-dynamic-kicker" data-qs-kicker>Atención en campo</span>
                    <h1 data-qs-title>Respuesta técnica para mantener tu maquinaria en marcha.</h1>
                    <p data-qs-copy>Envíanos el modelo, placa o fotografía de la pieza y te guiamos con el requerimiento.</p>
                    <form action="{{ route('storefront.products.index') }}" method="get" class="qs-hero-search qs-dynamic-search">
                        <input type="text" name="search" placeholder="Buscar repuesto o número de parte">
                        <button type="submit" aria-label="Buscar"><i class="fas fa-search"></i></button>
                    </form>
                    <div class="qs-hero-actions">
                        <a href="{{ route('storefront.products.index') }}">Explorar catálogo</a>
                        <a href="{{ route('storefront.contact') }}">Solicitar cotización</a>
                    </div>
                </div>

                <aside class="qs-dynamic-panel" aria-label="Accesos rápidos">
                    <a href="{{ route('storefront.products.index') }}"><span>01</span><strong>Repuestos</strong><small>Filtros, hidráulica, sensores y tren motriz.</small></a>
                    <a href="{{ route('storefront.services.index') }}"><span>02</span><strong>Diagnóstico</strong><small>Evaluación técnica para fallas recurrentes.</small></a>
                    <a href="{{ route('storefront.catalogs.index') }}"><span>03</span><strong>Catálogos</strong><small>Fichas, compatibilidades y recursos.</small></a>
                </aside>

                <div class="qs-dynamic-controls">
                    <button type="button" class="is-active" aria-label="Ver slide 1"></button>
                    <button type="button" aria-label="Ver slide 2"></button>
                    <button type="button" aria-label="Ver slide 3"></button>
                </div>
            </div>
        </section>

        <section class="qs-browse-section">
            <div class="container">
                <div class="qs-section-title">
                    <span>Categorías</span>
                    <div>
                        <h2>Explora por necesidad técnica</h2>
                    </div>
                </div>
                <div class="qs-browse-grid">
                    @foreach($browseCategories as $category)
                        <a href="{{ \App\Support\StorefrontUrl::catalog($category['value']) }}">
                            <span class="qs-browse-symbol" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">@switch($category['value'])@case('filtracion')<path d="M6 5h20L19 16v9l-6 3V16Z"/>@break @case('electrico')<path d="m18 3-12 16h9l-1 10 12-17h-9Z"/>@break @case('hidraulico')<path d="M11 3h10v5H11zm-2 5h14v15H9zm5 15h4v6h-4zM5 12h4m14 7h4"/>@break @default <path d="m14 3 4 0 1 4 4 2 4-1 2 4-3 3v4l3 3-2 4-4-1-4 2-1 4h-4l-1-4-4-2-4 1-2-4 3-3v-4l-3-3 2-4 4 1 4-2Z"/><circle cx="16" cy="17" r="5"/>@endswitch</svg></span>
                            <span>{{ $category['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="qs-brand-section" id="marcas">
            <div class="container qs-brand-grid">
                <div class="qs-brand-copy">
                    <span>Marcas y líneas</span>
                    <h2>Atendemos requerimientos para equipos agrícolas, industriales y de construcción.</h2>
                    <p>Trabajamos referencias, equivalencias y componentes compatibles para marcas de uso frecuente en operaciones exigentes.</p>
                </div>
                <div class="home-machine-grid" aria-label="Maquinaria por marca">
                    @foreach([['brand'=>'Volvo','file'=>'volvo','model'=>'EC210BLC','caption'=>'Excavadora sobre orugas'],['brand'=>'Komatsu','file'=>'komatsu','model'=>'PC200-10','caption'=>'Hidráulica y movimiento de tierra'],['brand'=>'Caterpillar','file'=>'caterpillar','model'=>'320','caption'=>'Excavadora hidráulica']] as $machine)
                    <a class="home-machine-card" href="{{ \App\Support\StorefrontUrl::catalog(null, ['marca' => $machine['brand']]) }}"><div class="home-machine-photo"><img src="{{ asset('storefront/images/machinery/'.$machine['file'].'-800.webp') }}" srcset="{{ asset('storefront/images/machinery/'.$machine['file'].'-800.webp') }} 800w, {{ asset('storefront/images/machinery/'.$machine['file'].'-1600.webp') }} 1600w" sizes="(max-width: 700px) 100vw, 33vw" alt="{{ $machine['brand'].' '.$machine['model'].' con la marca visible en la maquinaria' }}" width="800" height="530" loading="lazy" decoding="async"></div><div class="home-machine-body"><img src="{{ asset('storefront/images/brands/'.$machine['file'].'.svg') }}" alt="{{ $machine['brand'] }}" width="140" height="36" loading="lazy"><span>{{ $machine['caption'] }}</span><strong>Consultar repuestos <span aria-hidden="true">&rarr;</span></strong></div></a>
                    @endforeach
                </div>
                @include('storefront.partials.brand-marquee')
                @include('storefront.partials.home-machinery-credits')
                <div class="qs-line-list" aria-label="Líneas atendidas">
                    <span>Filtros</span>
                    <span>Hidráulica</span>
                    <span>Sensores</span>
                    <span>Transmisión</span>
                    <span>Motor</span>
                    <span>Mantenimiento</span>
                </div>
            </div>
        </section>

        @include('storefront.partials.home-quote-steps')

        <section class="qs-flash-section qs-product-showcase">
            <div class="container">
                <div class="qs-section-title">
                    <span>Productos</span>
                    <div>
                        <h2>Productos destacados</h2>
                        <p>Referencias listas para validar compatibilidad, disponibilidad y solicitud de cotización.</p>
                    </div>
                    <a href="{{ route('storefront.products.index') }}">Ver catálogo</a>
                </div>

                <div class="qs-featured-strip qs-featured-strip--two-rows">
                    @forelse($featuredProducts->take(6) as $product)
                        <article class="qs-product-card">
                            <a href="{{ \App\Support\StorefrontUrl::product($product) }}" class="qs-product-card__image">
                                <img src="{{ \App\Support\StorefrontUrl::productImage($product->image_path, asset('storefront/images/product-unavailable.svg')) }}" alt="{{ $product->name }}" width="320" height="240" loading="lazy" decoding="async" onerror="this.src='{{ asset('storefront/images/product-placeholder.svg') }}'">
                            </a>
                            <div class="qs-product-card__body">
                                <h3><a href="{{ \App\Support\StorefrontUrl::product($product) }}">{{ $product->name }}</a></h3>
                                @if($product->part_number)<p>Parte: {{ $product->part_number }}</p>@endif<strong class="home-product-price">{{ \App\Support\StorefrontPrice::label($product) }}</strong>
                                <a href="{{ \App\Support\StorefrontUrl::product($product) }}">Consultar</a>
                            </div>
                        </article>
                    @empty
                        <article class="qs-product-card qs-product-card--fallback">
                            <img src="{{ asset('storefront/images/hero-agro/spare-parts.webp') }}" alt="Componentes mecánicos para maquinaria industrial" loading="eager" decoding="async">
                            <strong>Filtros y mantenimiento</strong>
                            <span>Solicita referencias por modelo, marca o número de parte.</span>
                            <a href="{{ route('storefront.contact') }}">Solicitar asistencia</a>
                        </article>
                    @endforelse
                </div>
            </div>
        </section>

        @include('storefront.partials.home-whatsapp-banner')

        <section class="qs-promo-grid-section">
            <div class="container qs-promo-grid">
                <article class="qs-promo-card qs-promo-card--dark" style="--promo-image:url('{{ asset('storefront/images/machinery/promo-komatsu-800.webp') }}')">
                    <img src="{{ asset('storefront/images/machinery/promo-komatsu-800.webp') }}" alt="Excavadora Komatsu PC200" loading="eager" decoding="async">
                    <span>Servicio técnico</span>
                    <h2>Diagnóstico para fallas que se repiten.</h2>
                    <p>Analizamos síntomas, contexto de operación y piezas críticas para reducir paros recurrentes.</p>
                    <a href="{{ route('storefront.services.index') }}">Ver servicios</a>
                </article>
                <article class="qs-promo-card" style="--promo-image:url('{{ asset('storefront/images/machinery/promo-volvo-800.webp') }}')">
                    <img src="{{ asset('storefront/images/machinery/promo-volvo-800.webp') }}" alt="Excavadora Volvo EC210B" loading="eager" decoding="async">
                    <span>Compatibilidad</span>
                    <h3>¿No sabes el número de parte?</h3>
                    <p>Envíanos foto de la pieza, placa o modelo del equipo.</p>
                    <a href="{{ route('storefront.contact') }}">Solicitar ayuda</a>
                </article>
                <article class="qs-promo-card" style="--promo-image:url('{{ asset('storefront/images/machinery/promo-caterpillar-800.webp') }}')">
                    <img src="{{ asset('storefront/images/machinery/promo-caterpillar-800.webp') }}" alt="Excavadora Caterpillar 320" loading="eager" decoding="async">
                    <span>Biblioteca</span>
                    <h3>Catálogos y fichas técnicas</h3>
                    <p>Consulta recursos para compras y mantenimiento.</p>
                    <a href="{{ route('storefront.catalogs.index') }}">Ver recursos</a>
                </article>
            </div>
        </section>

        <section class="qs-difference-section">
            <div class="container qs-difference-grid">
                <div class="qs-difference-copy">
                    <span>Por qué elegirnos</span>
                    <h2>Más que vender repuestos, ayudamos a sostener tu operación.</h2>
                    <p>Quantum combina catálogo técnico, validación de compatibilidad, soporte en campo y seguimiento comercial para que cada compra tenga respaldo operativo.</p>
                    <div class="qs-difference-actions">
                        <a href="{{ route('storefront.contact') }}">Hablar con un asesor</a>
                        <a href="{{ route('storefront.blog.index') }}">Leer blog técnico</a>
                    </div>
                </div>
                <div class="qs-difference-list">
                    <article>
                        <b>01</b>
                        <div>
                            <strong>Validación antes de cotizar</strong>
                            <span>Revisamos número de parte, marca, modelo, fotos y condiciones de trabajo antes de recomendar una pieza.</span>
                        </div>
                    </article>
                    <article>
                        <b>02</b>
                        <div>
                            <strong>Catálogo y recursos técnicos</strong>
                            <span>Centralizamos productos, catálogos, fichas y guías para que compras y mantenimiento trabajen con mejor información.</span>
                        </div>
                    </article>
                    <article>
                        <b>03</b>
                        <div>
                            <strong>Soporte después de la venta</strong>
                            <span>Acompañamos compatibilidad, instalación, diagnóstico y continuidad operativa cuando la maquinaria no puede esperar.</span>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="qs-index-links-section">
            <div class="container qs-index-links-grid">
                <a href="{{ route('storefront.products.index') }}"><span>Productos</span><strong>Explora repuestos y componentes disponibles.</strong></a>
                <a href="{{ route('storefront.catalogs.index') }}"><span>Catálogos</span><strong>Consulta fichas, guías y documentos técnicos.</strong></a>
                <a href="{{ route('storefront.blog.index') }}"><span>Blog técnico</span><strong>Aprende sobre compatibilidad y mantenimiento.</strong></a>
                <a href="{{ route('storefront.contact') }}"><span>Contacto</span><strong>Solicita ayuda para identificar la pieza correcta.</strong></a>
            </div>
        </section>

        @include('storefront.partials.home-faq')

        <section class="qs-home-contact-section">
            <div class="container qs-home-contact">
                <div class="qs-home-contact__copy">
                    <span>Contáctanos</span>
                    <h2>Visítanos o envíanos tu requerimiento técnico.</h2>
                    <p>Comparte el modelo, placa, foto de la pieza o número de parte. Te ayudamos a validar compatibilidad y disponibilidad.</p>
                    <div class="qs-home-contact__links">
                        <a href="tel:+50583262783"><i class="fas fa-phone"></i> +505 8326 2783</a>
                        <a href="mailto:info@quantumsoluciones.com"><i class="fas fa-envelope"></i> info@quantumsoluciones.com</a>
                    </div>
                    <a class="qs-home-contact__cta" href="{{ route('storefront.contact') }}">Enviar solicitud</a>
                </div>
                <article class="qs-home-contact__map">
                    <span>Oficina principal</span>
                    <div data-map-consent-placeholder><strong>Consulta nuestra ubicación</strong><p>Para ver el mapa, autoriza la carga del servicio externo de Google Maps.</p><button type="button" data-cookie-open>Configurar mapas y privacidad</button></div><iframe loading="eager" data-consent-src="https://www.google.com/maps/embed?pb=!1m17!1m12!1m3!1d3901.031894646502!2d-86.25195692493783!3d12.109969088131827!2m3!1f0!2f0!3f0!3m2!1i1024!1i768!4f13.1!3m2!1m1!2zMTLCsDA2JzM1LjkiTiA4NsKwMTQnNTcuOCJX!5e0!3m2!1sen!2sni" title="Ubicación Quantum Soluciones" hidden></iframe>
                    <strong>Colonia Centro América, Managua</strong>
                </article>
            </div>
        </section>

        <section class="qs-benefits-section qs-benefits-section--final">
            <div class="container qs-benefits-grid">
                <article><b>24</b><strong>Respuesta ágil</strong><span>Atención priorizada para equipos detenidos.</span></article>
                <article><b>ST</b><strong>Soporte técnico</strong><span>Validación antes de cotizar o comprar.</span></article>
                <article><b>OK</b><strong>Compra confiable</strong><span>Compatibilidad, trazabilidad y seguimiento.</span></article>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.querySelector('[data-qs-hero]');
            if (!root) return;

            const slides = Array.from(root.querySelectorAll('.qs-dynamic-hero__slides img'));
            const dots = Array.from(root.querySelectorAll('.qs-dynamic-controls button'));
            const kicker = root.querySelector('[data-qs-kicker]');
            const title = root.querySelector('[data-qs-title]');
            const copy = root.querySelector('[data-qs-copy]');
            const content = [
                { kicker: 'Volvo · Repuestos y soporte', title: 'Respuesta técnica para mantener tu maquinaria en marcha.', copy: 'Comparte el modelo o número de parte. Validamos la referencia antes de cotizar.' },
                { kicker: 'Komatsu · Hidráulica y componentes', title: 'La pieza correcta para seguir trabajando.', copy: 'Revisamos aplicación, medidas y disponibilidad para preparar tu cotización.' },
                { kicker: 'Caterpillar · Maquinaria de construcción', title: 'Repuestos para tu próximo trabajo.', copy: 'Envía una foto o los datos del equipo. Te ayudamos a identificar la pieza.' }
            ];
            const reserveCopySpace = () => {
                root.style.setProperty('--home-header-height', Math.max(0, root.getBoundingClientRect().top + window.scrollY) + 'px');
                for (const [element, key] of [[title, 'title'], [copy, 'copy'], [kicker, 'kicker']]) {
                    const probe = element.cloneNode(false);
                    Object.assign(probe.style, {position:'absolute', visibility:'hidden', width:element.getBoundingClientRect().width+'px', minHeight:'0', height:'auto', animation:'none', transition:'none'});
                    element.parentNode.append(probe);
                    let height = 0;
                    content.forEach(item => { probe.textContent=item[key]; height=Math.max(height,probe.getBoundingClientRect().height); });
                    probe.remove();
                    element.style.minHeight=Math.ceil(height)+'px';
                }
            };
            document.fonts.ready.then(reserveCopySpace);
            let resizeTimer;
            window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer=setTimeout(reserveCopySpace,100); });
            let active = 0;
            let timer;
            const loadedSlides = new Set([0]);
            const slideLoads = new Map();

            const setSlide = index => {
                active = index;
                slides.forEach((slide, i) => slide.classList.toggle('is-active', i === active));
                dots.forEach((dot, i) => dot.classList.toggle('is-active', i === active));
                kicker.textContent = content[active].kicker;
                title.textContent = content[active].title;
                copy.textContent = content[active].copy;
                root.classList.remove('is-copy-in');
                void root.offsetWidth;
                root.classList.add('is-copy-in');
            };

            const play = () => {
                clearInterval(timer);
                timer = setInterval(() => activateSlide((active + 1) % slides.length), 6800);
            };

            const loadSlide = index => {
                if (loadedSlides.has(index)) return Promise.resolve(true);
                if (slideLoads.has(index)) return slideLoads.get(index);

                const slide = slides[index];
                const source = slide.dataset.src;
                if (!source) return Promise.resolve(slide.complete && slide.naturalWidth > 0);

                const pendingLoad = new Promise(resolve => {
                    const finish = loaded => {
                        slide.removeEventListener('load', onLoad);
                        slide.removeEventListener('error', onError);
                        if (loaded) loadedSlides.add(index);
                        slideLoads.delete(index);
                        resolve(loaded);
                    };
                    const onLoad = () => finish(true);
                    const onError = () => finish(false);

                    slide.addEventListener('load', onLoad, { once: true });
                    slide.addEventListener('error', onError, { once: true });
                    slide.src = source;
                    slide.removeAttribute('data-src');
                });

                slideLoads.set(index, pendingLoad);
                return pendingLoad;
            };

            const activateSlide = async index => {
                if (await loadSlide(index)) setSlide(index);
            };

            const hydrateDeferredSlides = async () => {
                await Promise.all(slides.slice(1).map((slide, index) => loadSlide(index + 1)));
                if (loadedSlides.size > 1) play();
            };

            const scheduleDeferredSlides = () => {
                const hydrate = () => hydrateDeferredSlides();
                window.setTimeout(() => {
                    if ('requestIdleCallback' in window) {
                        window.requestIdleCallback(hydrate, { timeout: 1500 });
                    } else {
                        hydrate();
                    }
                }, 1000);
            };

            dots.forEach((dot, index) => dot.addEventListener('click', async () => {
                clearInterval(timer);
                await activateSlide(index);
                play();
            }));

            setSlide(0);
            if (document.readyState === 'complete') {
                scheduleDeferredSlides();
            } else {
                window.addEventListener('load', scheduleDeferredSlides, { once: true });
            }
        });
    </script>
</x-layouts.storefront>
