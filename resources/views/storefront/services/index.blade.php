<x-layouts.storefront>
    <x-slot:title>Servicios - Quantum Soluciones</x-slot:title>
    <main class="about-page services-page">
        <div class="container">
            <section class="about-hero about-hero-premium services-hero-premium">
                <div class="about-hero-copy">
                    <span class="about-kicker">Servicios Quantum</span>
                    <h1>Soporte técnico para sostener productividad, no solo para resolver fallas.</h1>
                    <p>Combinamos mantenimiento, diagnóstico y suministro de refacciones para reducir incertidumbre operativa. Cada servicio se define por impacto, tiempo y entregables concretos.</p>
                    <div class="about-hero-chips">
                        <span><i class="fas fa-check"></i> Atención en campo</span>
                        <span><i class="fas fa-check"></i> Validación técnica</span>
                        <span><i class="fas fa-check"></i> Seguimiento operativo</span>
                    </div>
                </div>
                <aside class="about-hero-panel services-hero-panel">
                    <img class="storefront-context-image" src="{{ asset('storefront/images/modules/services-maintenance.webp') }}" alt="Técnicos realizando labores de mantenimiento" loading="eager" decoding="async">
                    <strong>Modalidad de atención</strong>
                    <ul>
                        <li><span>Diagnóstico</span><b>En sitio o remoto</b></li>
                        <li><span>Intervención</span><b>Programada y de emergencia</b></li>
                        <li><span>Seguimiento</span><b>Con indicadores y plan de mejora</b></li>
                    </ul>
                </aside>
            </section>

            <section class="services-section-intro">
                <span>Áreas de soporte</span>
                <h2>Servicios organizados para resolver, prevenir y abastecer con criterio técnico.</h2>
            </section>

            <section class="services-premium-grid services-premium-grid--ordered">
                <article class="services-premium-card">
                    <div class="services-premium-index">01</div>
                    <div class="services-premium-card-head"><i class="fas fa-screwdriver-wrench"></i><h3>Mantenimiento preventivo</h3></div>
                    <p>Rutinas de inspección y servicio para anticipar fallas antes de que afecten producción.</p>
                    <ul><li>Inspección por puntos críticos</li><li>Calendario por horas y temporada</li><li>Checklist y reporte técnico</li></ul>
                </article>
                <article class="services-premium-card">
                    <div class="services-premium-index">02</div>
                    <div class="services-premium-card-head"><i class="fas fa-stethoscope"></i><h3>Diagnóstico técnico</h3></div>
                    <p>Evaluación de síntomas, condiciones de trabajo y causa raíz para evitar reincidencias.</p>
                    <ul><li>Análisis de contexto operativo</li><li>Priorización por riesgo</li><li>Plan de corrección validado</li></ul>
                </article>
                <article class="services-premium-card">
                    <div class="services-premium-index">03</div>
                    <div class="services-premium-card-head"><i class="fas fa-boxes-stacked"></i><h3>Refacciones y suministro</h3></div>
                    <p>Identificación de piezas, equivalencias y disponibilidad para compras más confiables.</p>
                    <ul><li>Validación por SKU y N° parte</li><li>Inventario crítico recomendado</li><li>Trazabilidad de entregas</li></ul>
                </article>
                <article class="services-premium-card">
                    <div class="services-premium-index">04</div>
                    <div class="services-premium-card-head"><i class="fas fa-file-lines"></i><h3>Documentación técnica</h3></div>
                    <p>Registro de hallazgos, evidencias, recomendaciones y próximos pasos para mantenimiento.</p>
                    <ul><li>Reportes claros para compras</li><li>Historial de intervención</li><li>Soporte con catálogos y fichas</li></ul>
                </article>
            </section>

            <section class="services-benefits-band">
                <div><strong>Menos paros</strong><span>Planificación preventiva y atención priorizada.</span></div>
                <div><strong>Mejor compra</strong><span>Compatibilidad validada antes de cotizar.</span></div>
                <div><strong>Más claridad</strong><span>Reportes, evidencias y siguientes pasos.</span></div>
            </section>

            <section class="services-flow-premium">
                <div>
                    <span class="services-flow-kicker">Metodología</span>
                    <h2>Proceso de servicio en 4 etapas</h2>
                    <p class="services-flow-copy">Ordenamos cada solicitud desde el primer contacto hasta el seguimiento, para que la respuesta sea clara y accionable.</p>
                </div>
                <div class="services-flow-lane">
                    <article><span>01</span><h4>Evaluación inicial</h4><p>Identificamos riesgos, causas probables y urgencia operativa.</p></article>
                    <article><span>02</span><h4>Plan técnico</h4><p>Definimos tareas, tiempos y recursos priorizados por impacto.</p></article>
                    <article><span>03</span><h4>Ejecución</h4><p>Intervenimos con control técnico y validación de operación.</p></article>
                    <article><span>04</span><h4>Seguimiento</h4><p>Medimos resultados y proponemos mejoras preventivas.</p></article>
                </div>
            </section>

            <section class="services-result-panel">
                <div><h3>Resultado esperado</h3><p>Menos paros no programados, mayor disponibilidad de equipo y decisiones técnicas más claras.</p></div>
                <div class="about-cta-actions"><a href="{{ route('storefront.contact') }}" class="btn-primary">Solicitar asesoría</a><a href="{{ route('storefront.catalogs.index') }}" class="btn-secondary">Ver catálogos técnicos</a></div>
            </section>

            <section class="service-form-section"><livewire:storefront.service-request-form /></section>
        </div>
    </main>
</x-layouts.storefront>
