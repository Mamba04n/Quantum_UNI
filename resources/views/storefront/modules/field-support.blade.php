<x-layouts.storefront>
    <x-slot:title>Soporte en campo - Quantum Soluciones</x-slot:title>

    <main class="service-module-page">
        <section class="container service-module-hero">
            <div class="service-module-hero__copy">
                <span class="service-module-kicker">Soporte en campo</span>
                <h1>Atención técnica cuando el equipo no puede esperar.</h1>
                <p>Coordinamos revisión, diagnóstico y seguimiento para maquinaria agrícola e industrial en operación. El enfoque es llegar con información suficiente para decidir mejor y actuar con orden.</p>
                <div class="service-module-actions">
                    <a href="{{ route('storefront.contact') }}">Coordinar atención</a>
                    <a href="{{ route('storefront.services.index') }}">Ver servicios</a>
                </div>
            </div>
            <aside class="service-module-panel">
                <span>Importante</span>
                <strong>La atención en campo depende de ubicación, agenda, seguridad de acceso y disponibilidad técnica.</strong>
                <p>Antes de coordinar una visita validamos datos básicos y aprobación del alcance.</p>
            </aside>
        </section>

        <section class="container service-module-band">
            <div>
                <span class="service-module-kicker">Alcance</span>
                <h2>Campo, taller o soporte remoto según el caso.</h2>
                <p>Antes de coordinar una visita, levantamos datos básicos para estimar urgencia, herramientas, posibles repuestos y nivel de revisión requerido. Esto ayuda a evitar salidas improductivas y a priorizar equipos detenidos.</p>
            </div>
            <ul class="service-module-list">
                <li>Ubicación y condición operativa del equipo.</li>
                <li>Síntoma, historial y evidencia disponible.</li>
                <li>Herramientas, repuestos y complejidad estimada.</li>
                <li>Prioridad según impacto en la operación.</li>
            </ul>
        </section>

        <section class="container service-module-grid">
            <article><b>01</b><strong>Revisión inicial</strong><span>Levantamiento de información, prioridad operativa, ubicación y condición del equipo.</span></article>
            <article><b>02</b><strong>Intervención</strong><span>Soporte técnico según alcance, evidencia, disponibilidad y complejidad del problema.</span></article>
            <article><b>03</b><strong>Reporte</strong><span>Hallazgos, fotografías, acciones sugeridas, componentes requeridos y siguientes pasos.</span></article>
            <article><b>04</b><strong>Repuestos</strong><span>Validación de componentes necesarios para resolver o prevenir una falla repetitiva.</span></article>
            <article><b>05</b><strong>Seguimiento</strong><span>Acompañamiento posterior para validar funcionamiento o preparar una cotización complementaria.</span></article>
            <article><b>06</b><strong>Prioridad</strong><span>Clasificación del caso según impacto: equipo detenido, falla recurrente o mantenimiento programado.</span></article>
        </section>

        <section class="container service-module-cta">
            <div>
                <span class="service-module-kicker">Preparación</span>
                <h2>Prepara una solicitud de soporte.</h2>
                <p>Indica ubicación, marca, modelo, síntoma, nivel de urgencia y fotos. Con eso evaluamos la mejor ruta de atención.</p>
            </div>
            <a href="{{ route('storefront.contact') }}">Coordinar atención</a>
        </section>
    </main>
</x-layouts.storefront>
