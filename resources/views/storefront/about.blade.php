<x-layouts.storefront>
    <x-slot:title>Nosotros - Quantum Soluciones</x-slot:title>
    <main class="about-page about-refined-page">
        <div class="container">
            <section class="about-hero about-hero-premium">
                <div class="about-hero-copy">
                    <span class="about-kicker">Nosotros</span>
                    <h1>Operación estable, decisiones técnicas claras y respuesta en campo.</h1>
                    <p>Somos un equipo técnico orientado a continuidad operativa para maquinaria agrícola e industrial. Trabajamos con diagnóstico, ejecución y seguimiento para reducir paros no programados.</p>
                    <div class="about-hero-chips">
                        <span><i class="fas fa-bolt"></i> Respuesta ágil</span>
                        <span><i class="fas fa-gauge-high"></i> Enfoque en rendimiento</span>
                        <span><i class="fas fa-clipboard-check"></i> Trazabilidad técnica</span>
                    </div>
                </div>
                <aside class="about-hero-panel">
                    <img class="storefront-context-image" src="{{ asset('storefront/images/modules/about-team.webp') }}" alt="Equipo técnico colaborando en una planta industrial" loading="eager" decoding="async">
                    <strong>Enfoque Quantum</strong>
                    <ul>
                        <li><span>Especialidad</span><b>Mantenimiento + Refacciones</b></li>
                        <li><span>Atención</span><b>Agrícola e industrial</b></li>
                        <li><span>Modelo</span><b>Preventivo + Correctivo</b></li>
                    </ul>
                    <a href="{{ route('storefront.services.index') }}">Ver metodología <i class="fas fa-arrow-right"></i></a>
                </aside>
            </section>
            <section class="about-kpi-grid">
                <article><strong>10+</strong><span>Años de experiencia técnica acumulada</span></article>
                <article><strong>500+</strong><span>Intervenciones ejecutadas en campo</span></article>
                <article><strong>98%</strong><span>Continuidad operativa reportada por clientes</span></article>
                <article><strong>24/7</strong><span>Seguimiento en eventos críticos de operación</span></article>
            </section>
            <section class="about-story about-story-premium">
                <div class="about-story-copy">
                    <h2>Cómo trabajamos con tu operación</h2>
                    <p>Más que una visita puntual, operamos como una célula técnica extendida de tu empresa. Alineamos mantenimiento, abastecimiento crítico y seguimiento para estabilizar productividad.</p>
                    <p>Documentamos hallazgos, priorizamos acciones por impacto y acompañamos la ejecución hasta validar resultados.</p>
                </div>
                <div class="about-story-stats">
                    <div><strong>Diagnóstico</strong><span>Inspección inicial con riesgos priorizados</span></div>
                    <div><strong>Plan de acción</strong><span>Actividades, tiempos y responsables definidos</span></div>
                    <div><strong>Ejecución</strong><span>Intervención con control técnico en campo</span></div>
                    <div><strong>Seguimiento</strong><span>Medición de desempeño y mejora continua</span></div>
                </div>
            </section>
            <section class="about-values about-values-premium">
                <h2>Nuestros principios de servicio</h2>
                <div class="about-values-grid">
                    <article><h4><i class="fas fa-handshake"></i> Confianza operativa</h4><p>Comunicación clara, compromisos medibles y seguimiento permanente.</p></article>
                    <article><h4><i class="fas fa-stopwatch"></i> Velocidad con criterio</h4><p>Priorizamos lo que detiene producción para actuar donde más impacta.</p></article>
                    <article><h4><i class="fas fa-award"></i> Calidad técnica</h4><p>Ejecución con estándares, evidencia y validación de resultados.</p></article>
                    <article><h4><i class="fas fa-lightbulb"></i> Mejora continua</h4><p>Decisiones preventivas que reducen fallas repetitivas y costos ocultos.</p></article>
                </div>
            </section>
            <section class="about-cta about-cta-premium">
                <h3>Hagamos más estable tu operación en la próxima temporada</h3>
                <p>Te ayudamos a definir un plan técnico realista para tus equipos y tu contexto operativo.</p>
                <div class="about-cta-actions">
                    <a href="{{ route('storefront.contact') }}" class="btn-primary">Hablar con un asesor</a>
                    <a href="{{ route('storefront.services.index') }}" class="btn-secondary">Explorar servicios</a>
                </div>
            </section>
        </div>
    </main>
</x-layouts.storefront>
