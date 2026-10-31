<x-layouts.storefront>
    <x-slot:title>Cotizaciones y garant&iacute;as - Quantum Soluciones</x-slot:title>

    <main class="legal-page warranty-page">
        <section class="container legal-hero warranty-hero">
            <span>Cotizaciones y garant&iacute;as</span>
            <div>
                <h1>Hoja de garant&iacute;a est&aacute;ndar.</h1>
                <p>Clasificaci&oacute;n, vigencia y condiciones aplicables para repuestos, servicios y reclamos t&eacute;cnicos de Quantum Soluciones.</p>
                <small>&Uacute;ltima actualizaci&oacute;n: {{ now()->format('d/m/Y') }}</small>
            </div>
        </section>

        <section class="container legal-content legal-content--wide warranty-content">
            <section class="warranty-intro-panel">
                <div>
                    <span class="commerce-kicker">Garant&iacute;a visual por colores</span>
                    <p><strong>Garant&iacute;a limitada contra defectos de fabricaci&oacute;n.</strong> La cobertura inicia en la fecha de factura y se valida seg&uacute;n el c&oacute;digo de garant&iacute;a indicado en la cotizaci&oacute;n. La garant&iacute;a est&aacute;ndar general es de <strong>90 d&iacute;as o 250 horas motor</strong>, lo que ocurra primero; cuando el &iacute;tem tenga una garant&iacute;a espec&iacute;fica por clasificaci&oacute;n, prevalecer&aacute; el plazo se&ntilde;alado para ese &iacute;tem.</p>
                </div>
                <a href="{{ asset('pdf/GARANTIA.pdf') }}" target="_blank" rel="noopener">Ver PDF de garant&iacute;a</a>
            </section>

            <div class="warranty-color-legend" aria-label="L&iacute;neas de garant&iacute;a por color">
                <article class="warranty-line warranty-line--a">
                    <strong>L&iacute;nea A</strong>
                    <b>A1 / A2</b>
                </article>
                <article class="warranty-line warranty-line--b">
                    <strong>L&iacute;nea B</strong>
                    <b>B1 / B2</b>
                </article>
                <article class="warranty-line warranty-line--c">
                    <strong>L&iacute;nea C</strong>
                    <b>C1 / C2</b>
                </article>
                <article class="warranty-line warranty-line--plus">
                    <strong>Plus</strong>
                    <b>+50%</b>
                </article>
            </div>

            <section class="warranty-section">
                <div class="warranty-section-head">
                    <span class="legal-block-index">1</span>
                    <h2>Clasificaci&oacute;n y tiempo de garant&iacute;a</h2>
                </div>

                <div class="warranty-visual-table" role="table" aria-label="Clasificaci&oacute;n y tiempo de garant&iacute;a">
                    <div class="warranty-table-head" role="row">
                        <span>C&oacute;digo</span>
                        <span>Tiempo de garant&iacute;a</span>
                    </div>
                    <article class="warranty-row warranty-row--a1" role="row">
                        <b>A1</b>
                        <span>6-12 meses</span>
                    </article>
                    <article class="warranty-row warranty-row--a2" role="row">
                        <b>A2</b>
                        <span>6 meses</span>
                    </article>
                    <article class="warranty-row warranty-row--b1" role="row">
                        <b>B1</b>
                        <span>6 meses</span>
                    </article>
                    <article class="warranty-row warranty-row--b2" role="row">
                        <b>B2</b>
                        <span>2-3 meses</span>
                    </article>
                    <article class="warranty-row warranty-row--c1" role="row">
                        <b>C1</b>
                        <span>2 meses</span>
                    </article>
                    <article class="warranty-row warranty-row--c2" role="row">
                        <b>C2</b>
                        <span>1 mes</span>
                    </article>
                    <article class="warranty-row warranty-row--plus" role="row">
                        <b>A/B/C Plus</b>
                        <span>+50% del plazo</span>
                    </article>
                </div>
            </section>

            <section class="warranty-section warranty-customer">
                <div class="warranty-section-head">
                    <span class="legal-block-index">2</span>
                    <h2>Atenci&oacute;n al cliente</h2>
                </div>
                <p>Para solicitudes, consultas, asistencia o informaci&oacute;n y seguimiento de solicitudes por favor contacte nuestro equipo de atenci&oacute;n al cliente por los medios indicados. Nosotros estamos comprometidos con atender todas sus solicitudes y requerimientos a tiempo para cumplir y superar sus expectativas.</p>
            </section>

            <section class="warranty-section">
                <div class="warranty-section-head">
                    <span class="legal-block-index">3</span>
                    <h2>Condiciones principales para reclamos de garant&iacute;a</h2>
                </div>
                <div class="warranty-condition-board">
                    <article>
                        <h3>Cobertura</h3>
                        <ul>
                            <li>Defectos de fabricaci&oacute;n verificados t&eacute;cnicamente.</li>
                            <li>Reemplazo por componente equivalente cuando el reclamo sea aprobado.</li>
                            <li>En componentes mayores, se cubre la pieza interna averiada, no necesariamente el conjunto completo.</li>
                            <li>La garant&iacute;a PLUS extiende el periodo de cobertura en +50%.</li>
                        </ul>
                    </article>
                    <article>
                        <h3>Evidencias requeridas</h3>
                        <ul>
                            <li>Factura, n&uacute;mero de parte y datos del equipo.</li>
                            <li>Registro de mantenimiento preventivo.</li>
                            <li>An&aacute;lisis de aceite cuando aplique.</li>
                            <li>Fotograf&iacute;as de instalaci&oacute;n, hor&oacute;metro, falla y componente.</li>
                            <li>Contexto operacional del equipo: si el componente est&aacute; operando bajo condiciones normales.</li>
                        </ul>
                    </article>
                    <article>
                        <h3>Limitaciones</h3>
                        <ul>
                            <li>No cubre da&ntilde;os por mala instalaci&oacute;n, uso inadecuado, falta de mantenimiento o selecci&oacute;n incorrecta aprobada por el cliente.</li>
                            <li>Los tiempos de respuesta dependen de aprobaci&oacute;n y disponibilidad.</li>
                            <li>No cubre costos indirectos, mano de obra, paros de equipo o demoras.</li>
                            <li>Piezas de desgaste como cauchos, piezas de alta fricci&oacute;n o fatiga, sellos de grasa o externos, no son cubiertas.</li>
                        </ul>
                    </article>
                </div>
            </section>

            <section class="warranty-section">
                <div class="warranty-section-head">
                    <span class="legal-block-index">4</span>
                    <h2>Proceso de garant&iacute;a</h2>
                </div>
                <div class="warranty-process-line">
                    <article>
                        <b>1</b>
                        <strong>Reporte</strong>
                        <span>Notificar al asesor de ventas con factura y datos del equipo.</span>
                    </article>
                    <article>
                        <b>2</b>
                        <strong>Evidencia</strong>
                        <span>Enviar registros, fotograf&iacute;as y an&aacute;lisis requeridos sobre la falla del producto.</span>
                    </article>
                    <article>
                        <b>3</b>
                        <strong>Evaluaci&oacute;n</strong>
                        <span>Quantum y/o expertos validan la causa de la falla.</span>
                    </article>
                    <article>
                        <b>4</b>
                        <strong>Resoluci&oacute;n</strong>
                        <span>Aprobado el reclamo, se entrega una soluci&oacute;n ya sea con un componente o piezas para reparar el componente seg&uacute;n disponibilidad.</span>
                    </article>
                </div>
                <div class="warranty-important-note">
                    <strong>Importante:</strong>
                    <span>La garant&iacute;a debe quedar indicada en la cotizaci&oacute;n o factura correspondiente. Toda cobertura est&aacute; sujeta a diagn&oacute;stico t&eacute;cnico, aprobaci&oacute;n del reclamo y disponibilidad del producto.</span>
                </div>
            </section>
        </section>
    </main>
</x-layouts.storefront>
