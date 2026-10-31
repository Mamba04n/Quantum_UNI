<x-layouts.storefront>
    <x-slot:title>Mantenimiento preventivo - Quantum Soluciones</x-slot:title>

    <main class="service-module-page">
        <section class="container service-module-hero">
            <div class="service-module-hero__copy">
                <span class="service-module-kicker">Mantenimiento preventivo</span>
                <h1>Menos paros no programados, más continuidad operativa.</h1>
                <p>Apoyamos la planificación de rutinas, revisión de componentes críticos y reposición de consumibles para equipos agrícolas, industriales y de construcción.</p>
                <div class="service-module-actions">
                    <a href="{{ route('storefront.contact') }}">Solicitar apoyo</a>
                    <a href="{{ route('storefront.catalogs.index') }}">Ver recursos técnicos</a>
                </div>
            </div>
            <aside class="service-module-panel">
                <span>Enfoque</span>
                <strong>Planificación por horas, temporada, carga y condición real de trabajo.</strong>
                <p>Priorizamos piezas críticas, consumibles y evidencia técnica para evitar compras reactivas.</p>
            </aside>
        </section>

        <section class="container service-module-grid">
            <article><b>01</b><strong>Inspección programada</strong><span>Revisión por horas, temporada, carga, ambiente o condición de trabajo.</span></article>
            <article><b>02</b><strong>Kits y consumibles</strong><span>Filtros, sellos, lubricación, bandas y componentes de desgaste planificados por servicio.</span></article>
            <article><b>03</b><strong>Componentes críticos</strong><span>Hidráulica, motor, tren motriz, sensores y puntos donde una falla detiene la operación.</span></article>
            <article><b>04</b><strong>Inventario mínimo</strong><span>Recomendaciones de piezas de alta rotación para reducir tiempos muertos.</span></article>
            <article><b>05</b><strong>Prioridad técnica</strong><span>Clasificamos hallazgos por urgencia: inmediato, próximo servicio o monitoreo.</span></article>
            <article><b>06</b><strong>Seguimiento</strong><span>Hallazgos documentados, evidencia, recomendaciones y próximos pasos.</span></article>
        </section>

        <section class="container service-module-band">
            <div>
                <span class="service-module-kicker">Cuándo conviene</span>
                <h2>Antes de temporada alta, después de una falla repetida o cuando el equipo no puede detenerse.</h2>
                <p>El mantenimiento preventivo ayuda a comprar con anticipación, ordenar prioridades y evitar que una pieza pequeña termine provocando una reparación mayor.</p>
            </div>
            <ul class="service-module-list">
                <li>Equipos con alto uso diario o trabajo severo.</li>
                <li>Maquinaria con historial de fugas, temperatura o vibración.</li>
                <li>Operaciones que dependen de disponibilidad continua.</li>
                <li>Clientes que buscan planificar repuestos por calendario.</li>
            </ul>
        </section>

        <section class="container service-module-cta">
            <div>
                <span class="service-module-kicker">Siguiente paso</span>
                <h2>Planifiquemos una lista preventiva.</h2>
                <p>Comparte marca, modelo, horas y tipo de operación para ayudarte a preparar una ruta de mantenimiento.</p>
            </div>
            <a href="{{ route('storefront.contact') }}">Solicitar apoyo</a>
        </section>
    </main>
</x-layouts.storefront>
