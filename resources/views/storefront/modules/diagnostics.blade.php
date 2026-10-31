<x-layouts.storefront>
    <x-slot:title>Diagnóstico técnico - Quantum Soluciones</x-slot:title>

    <main class="service-module-page">
        <section class="container service-module-hero">
            <div class="service-module-hero__copy">
                <span class="service-module-kicker">Diagnóstico técnico</span>
                <h1>Evaluamos síntomas antes de recomendar una pieza.</h1>
                <p>Revisamos contexto de operación, evidencia disponible y posibles causas para evitar compras innecesarias. No todo problema se resuelve cambiando el primer componente sospechoso.</p>
                <div class="service-module-actions">
                    <a href="{{ route('storefront.contact') }}">Solicitar diagnóstico</a>
                    <a href="{{ route('storefront.modules.identify') }}">Identificar pieza</a>
                </div>
            </div>
            <aside class="service-module-panel">
                <span>Criterio técnico</span>
                <strong>Primero entendemos la falla; después definimos repuesto, prueba o servicio.</strong>
                <p>Esto reduce compras equivocadas y ayuda a priorizar la ruta correcta de solución.</p>
            </aside>
        </section>

        <section class="container service-module-process">
            <article><b>01</b><strong>Síntoma</strong><span>Identificamos qué falla, cuándo ocurre, frecuencia, condiciones de carga y efecto en la operación.</span></article>
            <article><b>02</b><strong>Evidencia</strong><span>Revisamos fotos, video, códigos, lecturas, fugas, desgaste, temperatura, presión o historial.</span></article>
            <article><b>03</b><strong>Hipótesis técnica</strong><span>Comparamos síntomas con componentes críticos, mantenimiento previo y condiciones de trabajo.</span></article>
            <article><b>04</b><strong>Recomendación</strong><span>Definimos si conviene repuesto, inspección, servicio, prueba adicional o seguimiento.</span></article>
        </section>

        <section class="container service-module-band">
            <div>
                <span class="service-module-kicker">Áreas frecuentes</span>
                <h2>Fallas hidráulicas, eléctricas y mecánicas con contexto operativo.</h2>
                <p>Organizamos la información para que la decisión sea clara: qué revisar, qué comprar y qué no conviene reemplazar sin validar.</p>
            </div>
            <div class="service-module-mini-grid">
                <article><strong>Fallas hidráulicas</strong><span>Fugas, baja presión, contaminación, sellos, cilindros, acoples y bombas.</span></article>
                <article><strong>Fallas eléctricas</strong><span>Sensores, conectores, alimentación, señales intermitentes y componentes electrónicos.</span></article>
                <article><strong>Desgaste mecánico</strong><span>Rodamientos, tren motriz, transmisión, vibración, ruido y fatiga.</span></article>
            </div>
        </section>

        <section class="container service-module-cta">
            <div>
                <span class="service-module-kicker">Solicitud técnica</span>
                <h2>Cuéntanos qué está pasando con el equipo.</h2>
                <p>Una descripción clara del síntoma puede evitar una compra equivocada y acelerar la solución.</p>
            </div>
            <a href="{{ route('storefront.contact') }}">Solicitar diagnóstico</a>
        </section>
    </main>
</x-layouts.storefront>
