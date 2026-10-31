<x-layouts.storefront>
    <x-slot:title>Fichas t&eacute;cnicas - Quantum Soluciones</x-slot:title>

    <main class="info-module-page qs-refined-page">
        <section class="container info-module-hero qs-feature-hero">
            <div class="qs-feature-hero__copy">
                <span>Fichas y documentos</span>
                <h1>Informaci&oacute;n t&eacute;cnica para decidir con seguridad.</h1>
                <p>Convierte una solicitud ambigua en una compra verificable: referencia, aplicaci&oacute;n, medidas, equivalencias e instalaci&oacute;n.</p>
                <div class="qs-feature-pills"><span>Compatibilidad</span><span>Instalaci&oacute;n</span><span>Garant&iacute;a</span></div>
            </div>
            <figure class="qs-feature-hero__visual">
                <img src="{{ asset('storefront/images/modules/catalogs-manuals.webp') }}" alt="Documentos técnicos revisados junto a una computadora" loading="eager" decoding="async">
                <figcaption><strong>Antes de comprar</strong><span>Confirma pieza, aplicación y condiciones de trabajo.</span></figcaption>
            </figure>
        </section>

        <section class="container info-module-split">
            <div>
                <span class="info-module-kicker">Biblioteca t&eacute;cnica</span>
                <h2>Documentos pensados para operaciones reales.</h2>
                <p>En maquinaria agr&iacute;cola e industrial, una pieza incorrecta puede provocar retrasos, reprocesos o fallas repetitivas. Por eso organizamos recursos que permiten validar antes de comprar y documentar mejor cada requerimiento.</p>
                <p>Cuando una ficha no est&aacute; disponible, nuestro equipo puede orientar qu&eacute; datos se necesitan para construir una solicitud t&eacute;cnica clara.</p>
            </div>
            <div class="info-module-note">
                <strong>Dato clave</strong>
                <p>Una buena ficha no solo dice qu&eacute; pieza comprar. Tambi&eacute;n explica d&oacute;nde aplica, qu&eacute; revisar antes de instalarla y qu&eacute; evidencia conservar si luego se requiere garant&iacute;a.</p>
            </div>
        </section>

        <section class="container info-module-grid">
            <article><strong>Datos de pieza</strong><span>N&uacute;mero de parte, medidas, aplicaciones, material, equivalencias y notas de compatibilidad.</span></article>
            <article><strong>Instalaci&oacute;n y uso</strong><span>Recomendaciones para montaje, limpieza, torque, lubricaci&oacute;n, purga o verificaci&oacute;n inicial cuando aplique.</span></article>
            <article><strong>Mantenimiento</strong><span>Intervalos sugeridos, se&ntilde;ales de desgaste, condiciones de operaci&oacute;n y controles preventivos.</span></article>
            <article><strong>Garant&iacute;a</strong><span>Evidencia necesaria, condiciones de cobertura y criterios que pueden invalidar un reclamo.</span></article>
            <article><strong>Comparaci&oacute;n</strong><span>Alternativas originales, OEM, premium o aftermarket con observaciones de uso y riesgo.</span></article>
            <article><strong>Solicitud t&eacute;cnica</strong><span>Formato de datos m&iacute;nimos para cotizar sin perder tiempo: foto, placa, modelo, serie y aplicaci&oacute;n.</span></article>
        </section>

        <section class="container info-module-band">
            <div>
                <span class="info-module-kicker">Cu&aacute;ndo usar una ficha</span>
                <h2>Antes de cotizar, instalar o reclamar.</h2>
                <p>Las fichas son especialmente &uacute;tiles en filtros, hidr&aacute;ulica, sensores, tren motriz, rodamientos y componentes donde existen referencias visualmente parecidas pero con especificaciones distintas.</p>
            </div>
            <ul class="info-module-checklist">
                <li>Antes de aprobar una compra de repuesto cr&iacute;tico.</li>
                <li>Cuando hay dudas entre dos equivalencias.</li>
                <li>Antes de instalar una pieza el&eacute;ctrica, hidr&aacute;ulica o de sellado.</li>
                <li>Al preparar evidencia para soporte, diagn&oacute;stico o garant&iacute;a.</li>
            </ul>
        </section>

        <section class="container info-module-cta">
            <h2>Explora la biblioteca disponible o solicita una ficha.</h2>
            <p>Si tienes una referencia espec&iacute;fica, podemos ayudarte a revisar qu&eacute; documento aplica y qu&eacute; datos conviene confirmar.</p>
            <a href="{{ route('storefront.catalogs.index') }}">Ver cat&aacute;logos t&eacute;cnicos</a>
        </section>
    </main>
</x-layouts.storefront>
