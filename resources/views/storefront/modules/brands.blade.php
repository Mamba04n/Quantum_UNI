<x-layouts.storefront>
    <x-slot:title>Marcas que atendemos - Quantum Soluciones</x-slot:title>

    <main class="info-module-page qs-refined-page brands-refined-page">
        <section class="container info-module-hero qs-feature-hero qs-feature-hero--brands">
            <div class="qs-feature-hero__copy">
                <span>Marcas y l&iacute;neas</span>
                <h1>La marca inicia la búsqueda. La aplicación confirma la pieza.</h1>
                <p>Validamos referencias, equivalencias, condiciones de uso y disponibilidad para que cada compra tenga sentido operativo.</p>
                <div class="qs-feature-pills"><span>Agrícola</span><span>Industrial</span><span>Construcción</span></div>
            </div>
            <div class="qs-brand-hero-board" aria-label="Marcas con atención frecuente">
                <img src="{{ asset('storefront/images/brands/volvo.svg') }}" alt="Volvo">
                <img src="{{ asset('storefront/images/brands/komatsu.svg') }}" alt="Komatsu">
                <img src="{{ asset('storefront/images/brands/caterpillar.svg') }}" alt="Caterpillar">
                <strong>CUMMINS</strong><strong>MACK</strong><strong>SANY</strong>
            </div>
        </section>

        <section class="container info-module-split">
            <div>
                <span class="info-module-kicker">Cobertura principal</span>
                <h2>Marcas frecuentes en operaciones exigentes.</h2>
                <p>Quantum Soluciones apoya solicitudes de repuestos, componentes, consumibles y soporte t&eacute;cnico para equipos usados en agricultura, construcci&oacute;n, transporte pesado, miner&iacute;a ligera y operaci&oacute;n industrial.</p>
                <p>Cuando una referencia no est&aacute; disponible, revisamos alternativas compatibles y dejamos claro el nivel de respaldo esperado: original, OEM premium, aftermarket premium o alternativa de menor costo.</p>
            </div>
            <div class="brand-logo-board">
                <article class="brand-logo-card">
                    <img src="{{ asset('storefront/images/brands/volvo.svg') }}" alt="Volvo">
                    <span>Construcci&oacute;n, transporte y maquinaria pesada</span>
                </article>
                <article class="brand-logo-card">
                    <img src="{{ asset('storefront/images/brands/komatsu.svg') }}" alt="Komatsu">
                    <span>Movimiento de tierra y equipos industriales</span>
                </article>
                <article class="brand-logo-card">
                    <img src="{{ asset('storefront/images/brands/caterpillar.svg') }}" alt="Caterpillar">
                    <span>Motor, tren motriz, filtros y mantenimiento</span>
                </article>
                <article class="brand-logo-card brand-logo-card--text">
                    <strong>CUMMINS</strong>
                    <span>Motores, filtros, sensores y perif&eacute;ricos</span>
                </article>
                <article class="brand-logo-card brand-logo-card--text">
                    <strong>MACK</strong>
                    <span>Transporte pesado y componentes cr&iacute;ticos</span>
                </article>
                <article class="brand-logo-card brand-logo-card--text">
                    <strong>SANY</strong>
                    <span>Construcci&oacute;n, hidr&aacute;ulica y desgaste</span>
                </article>
            </div>
        </section>

        <section class="container info-module-grid">
            <article><strong>Motor y tren motriz</strong><span>Filtros, bandas, sensores, sellos, rodamientos, transmisiones y componentes asociados a continuidad operativa.</span></article>
            <article><strong>Hidr&aacute;ulica y sellado</strong><span>Cilindros, mangueras, sellos, acoples, bombas y referencias donde las medidas y la presi&oacute;n de trabajo importan.</span></article>
            <article><strong>Mantenimiento y consumibles</strong><span>Kits preventivos, lubricaci&oacute;n, filtros y piezas de desgaste para programar compras antes de una falla.</span></article>
        </section>

        <section class="container info-module-band">
            <div>
                <span class="info-module-kicker">C&oacute;mo validamos</span>
                <h2>La marca es solo el punto de partida.</h2>
                <p>Para confirmar una pieza usamos n&uacute;mero de parte, modelo, serie, fotograf&iacute;as, medidas, contexto de operaci&oacute;n y condiciones de instalaci&oacute;n. Esto evita comprar por parecido visual cuando la aplicaci&oacute;n requiere una especificaci&oacute;n precisa.</p>
            </div>
            <ul class="info-module-checklist">
                <li>Confirmaci&oacute;n de referencia original o equivalente.</li>
                <li>Revisi&oacute;n de aplicaci&oacute;n por modelo y uso real del equipo.</li>
                <li>Comparaci&oacute;n entre opciones original, OEM y aftermarket.</li>
                <li>Observaciones sobre disponibilidad, garant&iacute;a y riesgo t&eacute;cnico.</li>
            </ul>
        </section>

        <section class="container info-module-cta">
            <h2>Si tu marca no aparece, igual podemos ayudarte.</h2>
            <p>Env&iacute;anos placa, modelo, foto de la pieza o n&uacute;mero de parte. Te indicamos si podemos validar equivalencia, alternativa o una ruta de b&uacute;squeda m&aacute;s precisa.</p>
            <a href="{{ route('storefront.contact') }}">Solicitar validaci&oacute;n</a>
        </section>
    </main>
</x-layouts.storefront>
