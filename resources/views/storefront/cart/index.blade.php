<main class="cart-page quote-cart-page">
    <div class="container">
        <nav class="quote-cart-breadcrumb" aria-label="Ubicación"><a href="{{ route('storefront.home') }}">Inicio</a><span aria-hidden="true">/</span><a href="{{ route('storefront.products.index') }}">Repuestos</a><span aria-hidden="true">/</span><span aria-current="page">Carrito de cotización</span></nav>
        
        @if($submittedQuote)
            <section class="quote-cart-confirmation-card">
                <div class="quote-conf-header">
                    <div class="quote-conf-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    </div>
                    <div>
                        <span class="quote-conf-badge">Entrada directa a Licitación de Proveedores</span>
                        <h1 class="quote-conf-title">Solicitud {{ $submittedQuote['code'] }} registrada.</h1>
                        <p class="quote-conf-subtitle">Tu requerimiento de cotización ya ingresó a nuestro sistema comercial y se encuentra en fase de licitación.</p>
                    </div>
                </div>

                <div class="quote-conf-grid">
                    <div class="quote-conf-panel">
                        <h2>Canales de contacto y respuesta</h2>
                        <ul class="quote-conf-list">
                            <li>
                                <strong>WhatsApp de confirmación:</strong>
                                <span>{{ $submittedQuote['phone'] }}</span>
                            </li>
                            <li>
                                <strong>Correo para envío de cotización:</strong>
                                <span>{{ $submittedQuote['email'] }}</span>
                            </li>
                            <li>
                                <strong>Cliente / Solicitante:</strong>
                                <span>{{ $submittedQuote['name'] }}</span>
                            </li>
                        </ul>
                        <p class="quote-conf-note">Nuestro equipo de compras y ventas procesará la cotización con los mejores precios de proveedores y te contactará a la brevedad.</p>
                    </div>

                    <div class="quote-conf-panel quote-conf-portal">
                        <h2>Tu Portal de Cliente Quantum</h2>
                        @if($submittedQuote['account_created'])
                            <p class="quote-conf-portal-desc">Hemos creado tu cuenta de acceso exclusivo para que puedas consultar el estado de esta y futuras cotizaciones, ofertas y pedidos en tiempo real:</p>
                            <div class="quote-conf-creds">
                                <div><small>Usuario / Correo:</small><strong>{{ $submittedQuote['email'] }}</strong></div>
                                @if($submittedQuote['temp_password'])
                                    <div><small>Contraseña temporal:</small><strong class="quote-conf-pwd">{{ $submittedQuote['temp_password'] }}</strong></div>
                                @endif
                            </div>
                            <p class="quote-conf-pwd-tip">Guarda tu contraseña para ingresar. Podrás modificarla en cualquier momento desde tu perfil.</p>
                            <a href="{{ route('login') }}" class="products-add-btn quote-conf-btn">Ingresar a Mi Portal de Cliente →</a>
                        @elseif($submittedQuote['is_logged_in'])
                            <p class="quote-conf-portal-desc">Estás conectado con tu cuenta. Esta cotización ha quedado vinculada automáticamente a tu historial para que le des seguimiento en todo momento.</p>
                            <a href="{{ route('customer.portal', ['section' => 'quotations']) }}" class="products-add-btn quote-conf-btn">Ir a Mis Cotizaciones en el Portal →</a>
                        @else
                            <p class="quote-conf-portal-desc">Tu correo ya cuenta con registro en Quantum. Inicia sesión para ver el avance de tu cotización en tu panel:</p>
                            <a href="{{ route('login') }}" class="products-add-btn quote-conf-btn">Iniciar sesión en Mi Portal →</a>
                        @endif
                    </div>
                </div>

                <div class="quote-conf-footer">
                    <a href="{{ route('storefront.products.index') }}" class="quote-cart-continue">← Explorar más repuestos en el catálogo</a>
                    <a href="{{ route('storefront.home') }}" class="quote-conf-home-link">Volver al inicio</a>
                </div>
            </section>
        @else
            <header class="quote-cart-heading"><div><h1>Tu carrito de cotización <span>({{ $items->sum('cart_quantity') }})</span></h1><p>Revisa tus repuestos y envíanos la solicitud.</p></div>
                @if($items->isNotEmpty())<button type="button" wire:click="clear" wire:confirm="¿Vaciar el carrito de cotización?" wire:loading.attr="disabled" class="quote-cart-clear">Vaciar carrito</button>@endif
            </header>
            @if(session('success'))<p class="contact-status success" role="status">{{ session('success') }}</p>@endif
            @error('cart')<p class="contact-status error" role="alert">{{ $message }}</p>@enderror
            @if($items->isNotEmpty())
            <div class="quote-cart-layout">
                <div class="quote-cart-main">
                    <section aria-label="Repuestos seleccionados" class="quote-cart-lines">
                        @foreach($items as $item)
                        @php($line = $summary['lines'][$item->id])
                        <article class="quote-cart-item" wire:key="cart-line-{{ $item->id }}">
                            <a class="quote-cart-photo" href="{{ \App\Support\StorefrontUrl::product($item) }}"><img src="{{ \App\Support\StorefrontUrl::productImage($item->image_path) }}" alt="{{ $item->name }}" width="112" height="112">@unless($item->image_path)<small>Imagen de referencia</small>@endunless</a>
                            <div class="quote-cart-item-copy"><p class="quote-cart-part">{{ $item->brand }}{{ $item->brand ? ' · ' : '' }}{{ $item->part_number ?: 'Referencia por confirmar' }}</p><h2><a href="{{ \App\Support\StorefrontUrl::product($item) }}">{{ $item->name }}</a></h2><span class="quote-cart-stock {{ $item->stock_on_hand > 0 ? 'is-in' : 'is-out' }}">{{ $item->stock_on_hand > 0 ? 'En stock' : 'Consultar disponibilidad' }}</span>
                                @if($item->cart_quantity > $item->stock_on_hand && $item->stock_on_hand > 0)<p class="cart-stock-notice">{{ $item->stock_on_hand }} unidades en inventario. Confirmaremos el plazo para las restantes.</p>@endif
                            </div>
                            <div class="quote-cart-item-actions"><div class="quote-cart-price"><strong>{{ $line['totalUsd'] !== null ? 'US$'.number_format($line['totalUsd'],2) : 'Por cotizar' }}</strong>@if($line['unitUsd'] !== null)<small>US${{ number_format($line['unitUsd'],2) }} por unidad</small>@endif</div>
                                <div class="quote-cart-controls"><div class="commerce-quantity"><button type="button" wire:click="changeQuantity({{ $item->id }}, -1)" wire:loading.attr="disabled" @disabled($item->cart_quantity <= 1) aria-label="Reducir cantidad de {{ $item->name }}">−</button><input type="number" min="1" max="999" wire:model="quantities.{{ $item->id }}" wire:change="updateQuantity({{ $item->id }})" aria-label="Cantidad de {{ $item->name }}"><button type="button" wire:click="changeQuantity({{ $item->id }}, 1)" wire:loading.attr="disabled" @disabled($item->cart_quantity >= 999) aria-label="Aumentar cantidad de {{ $item->name }}">+</button></div>
                                    <button type="button" class="quote-cart-remove" wire:click="remove({{ $item->id }})" wire:loading.attr="disabled" aria-label="Eliminar {{ $item->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg></button>
                                </div>@error('quantities.'.$item->id)<p class="contact-status error">{{ $message }}</p>@enderror
                            </div>
                        </article>
                        @endforeach
                    </section>
                    <a class="quote-cart-continue" href="{{ route('storefront.products.index') }}">← Seguir explorando repuestos</a>
                    
                    <section class="quote-cart-contact" aria-labelledby="quote-contact-title">
                        <div class="commerce-account-invite">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3"/></svg>
                            <div>@guest<strong>Cotiza también con tu cuenta Quantum</strong><p>Inicia sesión o crea tu cuenta de cliente. Podrás dar seguimiento a tus cotizaciones y pedidos en tiempo real.</p><div class="commerce-invite-links"><a href="{{ route('customer.quote') }}">Ingresar y cotizar →</a><a href="{{ route('register', ['continue' => 'quote']) }}">Crear mi cuenta</a></div>@else<strong>Estás cotizando con tu sesión iniciada</strong><p>Completa los datos de contacto para esta cotización antes de enviar.</p><a href="{{ route('customer.portal') }}">Ir a mi portal →</a>@endguest</div>
                        </div>

                        <form id="cart-quote-form" wire:submit="requestQuote" autocomplete="off" class="secure-contact-form cart-quote-form">
                            <!-- Bloque 1: Cliente, Cuenta y Contacto -->
                            <div class="quote-cart-section-heading">
                                <span>Datos de cliente y cuenta</span>
                                <h2 id="quote-contact-title">¿A quién enviamos la cotización?</h2>
                                <p>Información de contacto y credenciales de acceso para tu portal de seguimiento.</p>
                            </div>
                            <div class="quote-cart-form-grid">
                                <label>Nombre del cliente o empresa *
                                    <input wire:model="name" autocomplete="off" placeholder="Nombre completo o razón social" required>
                                    @error('name')<span class="quote-field-error">{{ $message }}</span>@enderror
                                </label>
                                <label>Persona de contacto <span class="quote-optional">(opcional)</span>
                                    <input wire:model="contact_name" autocomplete="off" placeholder="Nombre de quien recibe">
                                    @error('contact_name')<span class="quote-field-error">{{ $message }}</span>@enderror
                                </label>
                                <label>Correo electrónico (cuenta y cotización) *
                                    <input wire:model="email" type="email" autocomplete="off" placeholder="tu@correo.com" required>
                                    @error('email')<span class="quote-field-error">{{ $message }}</span>@enderror
                                </label>
                                @guest
                                <label>Contraseña de acceso a tu portal <span class="quote-optional">(opcional)</span>
                                    <input wire:model="password" type="password" autocomplete="new-password" placeholder="Define tu clave o te generamos una">
                                    @error('password')<span class="quote-field-error">{{ $message }}</span>@enderror
                                </label>
                                @else
                                <label>Estado de cuenta Quantum
                                    <input type="text" value="Sesión activa: {{ auth()->user()->email }}" disabled style="background: #f8fafc; color: #475569; font-weight: 600;">
                                </label>
                                @endguest
                                <label>Teléfono / WhatsApp de contacto directo *
                                    <input wire:model="phone" type="tel" autocomplete="off" placeholder="Número con código de país" required>
                                    @error('phone')<span class="quote-field-error">{{ $message }}</span>@enderror
                                </label>
                                <label>RUC o Identificación fiscal <span class="quote-optional">(opcional)</span>
                                    <input wire:model="ruc" placeholder="Ej. J0310000000000">
                                    @error('ruc')<span class="quote-field-error">{{ $message }}</span>@enderror
                                </label>
                            </div>

                            <!-- Bloque 2: Equipo y Maquinaria -->
                            <div class="quote-cart-section-heading" style="margin-top: 22px;">
                                <span>Equipo y aplicación <span class="quote-optional">(opcional pero recomendado)</span></span>
                                <h2>¿En qué maquinaria se instalarán los repuestos?</h2>
                                <p>Nos ayuda a verificar compatibilidad técnica exacta antes de solicitar oferta a los fabricantes.</p>
                            </div>
                            <div class="quote-cart-form-grid">
                                <label>Tipo de maquinaria
                                    <input wire:model="machinery" placeholder="Ej. Excavadora, Tractor, Camión, Generador">
                                    @error('machinery')<span class="quote-field-error">{{ $message }}</span>@enderror
                                </label>
                                <label>Marca del equipo
                                    <input wire:model="machine_brand" placeholder="Ej. Caterpillar, Komatsu, Cummins">
                                    @error('machine_brand')<span class="quote-field-error">{{ $message }}</span>@enderror
                                </label>
                                <label>Modelo del equipo
                                    <input wire:model="machine_model" placeholder="Ej. 320D, D6T, PC200-8">
                                    @error('machine_model')<span class="quote-field-error">{{ $message }}</span>@enderror
                                </label>
                                <label>Número de serie / VIN
                                    <input wire:model="equipment_serial" placeholder="Serie del equipo o motor">
                                    @error('equipment_serial')<span class="quote-field-error">{{ $message }}</span>@enderror
                                </label>
                            </div>

                            <!-- Bloque 3: Urgencia y Notas -->
                            <div class="quote-cart-section-heading" style="margin-top: 22px;">
                                <span>Detalles de la cotización</span>
                                <h2>Prioridad y requerimientos especiales</h2>
                            </div>
                            <div class="quote-cart-form-grid">
                                <label style="grid-column: 1 / -1;">Nivel de urgencia
                                    <select wire:model="urgency" style="height: 44px; border-radius: 8px; border: 1px solid var(--qs-line); padding: 0 12px; font-size: 14px; background: #fff;">
                                        <option value="baja">Baja - Planificación regular</option>
                                        <option value="media">Media - Mantenimiento estándar</option>
                                        <option value="alta">Alta - Parada próxima</option>
                                        <option value="urgente">Urgente - Máquina parada</option>
                                    </select>
                                    @error('urgency')<span class="quote-field-error">{{ $message }}</span>@enderror
                                </label>
                            </div>

                            <label style="margin-top: 14px;"><span>Notas adicionales, modelos de componentes o especificaciones <span class="quote-optional">(opcional)</span></span>
                                <textarea wire:model="notes" rows="3" placeholder="Indica detalles adicionales como horas de operación, número de motor o especificaciones particulares..."></textarea>
                                @error('notes')<span class="quote-field-error">{{ $message }}</span>@enderror
                            </label>
                        </form>
                    </section>
                </div>
                
                <aside class="quote-cart-summary" aria-labelledby="quote-summary-title"><h2 id="quote-summary-title">Resumen de tu solicitud</h2>
                    <section class="cart-shipping-goal"><div class="cart-shipping-title"><strong>Envío gratis</strong><span>Desde US${{ number_format($summary['thresholdUsd'],0) }}</span></div><progress max="100" value="{{ $summary['progress'] }}" aria-label="Progreso para envío gratis">{{ round($summary['progress']) }}%</progress><p>@if($summary['reached'])Tu solicitud alcanza el monto para envío gratis.@elseif($summary['remainingUsd'] !== null)Faltan <strong>US${{ number_format($summary['remainingUsd'],2) }}</strong> para alcanzar el monto de envío gratis.@else Confirmaremos el monto al validar los precios.@endif</p></section>
                    <dl class="quote-cart-totals"><div><dt>Piezas seleccionadas</dt><dd>{{ $items->sum('cart_quantity') }}</dd></div><div class="quote-cart-subtotal"><dt>Subtotal referencial</dt><dd><strong>{{ $summary['usd'] !== null ? 'US$'.number_format($summary['usd'],2) : 'Por confirmar' }}</strong>@if($summary['nio'] !== null)<small>≈ C${{ number_format($summary['nio'],2) }}</small>@endif</dd></div></dl>
                    <p class="quote-cart-disclaimer">Precio y condiciones de entrega sujetos a confirmación. Esta solicitud no realiza un cobro.</p>
                    <button type="submit" form="cart-quote-form" class="products-add-btn quote-cart-submit" wire:loading.attr="disabled" wire:target="requestQuote"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6 3h9l4 4v14H6V3Zm8 0v5h5M9 12h7m-7 4h7"/></svg><span wire:loading.remove wire:target="requestQuote">Solicitar cotización formal</span><span wire:loading wire:target="requestQuote">Enviando a cotización…</span></button>
                    <p class="quote-cart-submit-note">Tu cotización entrará directamente al área de Licitaciones.</p>
                    <section class="quote-cart-benefits"><h3>Cotiza con confianza</h3>
                        <div><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4v6c0 4-8 8-8 8s-8-4-8-8V7l8-4Z"/><path d="m8 12 3 3 5-6"/></svg><p><strong>Compatibilidad verificada</strong><span>Revisamos la aplicación para tu equipo.</span></p></div>
                        <div><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 14v-3a8 8 0 0 1 16 0v3M4 12H2v7h4v-7H4Zm16 0h2v7h-4v-7h2ZM18 19v2h-6"/></svg><p><strong>Asesoría técnica directa</strong><span>Te ayudamos a identificar la pieza correcta.</span></p></div>
                        <div><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v12H9l-5 4V4Zm4 4h8m-8 4h5"/></svg><p><strong>Seguimiento de tu solicitud</strong><span>Acceso a tu portal de cliente con trazabilidad.</span></p></div>
                    </section>
                </aside>
            </div>
            @else
                <section class="quote-cart-empty"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 4h4l4 18h16M8 9h22l-4 10H10"/><circle cx="12" cy="27" r="1.5"/><circle cx="25" cy="27" r="1.5"/></svg><h2>Tu carrito está vacío</h2><p>Explora el catálogo y agrega los repuestos que necesitas cotizar.</p><a class="products-add-btn" href="{{ route('storefront.products.index') }}">Explorar repuestos</a></section>
            @endif
        @endif
<style>
.quote-cart-confirmation-card {
    background: #fff;
    border: 1px solid var(--qs-line, #e2e8f0);
    border-radius: 20px;
    padding: 36px;
    margin-top: 24px;
    box-shadow: 0 4px 20px -2px rgba(0,0,0,0.05);
}
.quote-conf-header {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    border-bottom: 1px solid var(--qs-line, #e2e8f0);
    padding-bottom: 24px;
    margin-bottom: 28px;
}
.quote-conf-icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: #ecfdf5;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.quote-conf-icon svg { width: 28px; height: 28px; }
.quote-conf-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: #ecfdf5;
    color: #059669;
    padding: 3px 10px;
    border-radius: 9999px;
    margin-bottom: 8px;
}
.quote-conf-title {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 6px 0;
}
.quote-conf-subtitle {
    font-size: 14px;
    color: #64748b;
    margin: 0;
}
.quote-conf-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}
@media (max-width: 768px) {
    .quote-conf-grid { grid-template-columns: 1fr; }
    .quote-conf-header { flex-direction: column; }
    .quote-cart-confirmation-card { padding: 22px; }
}
.quote-conf-panel {
    background: #f8fafc;
    border: 1px solid var(--qs-line, #e2e8f0);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    flex-direction: column;
}
.quote-conf-panel h2 {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 16px 0;
}
.quote-conf-list {
    list-style: none;
    padding: 0;
    margin: 0 0 16px 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
    font-size: 13px;
}
.quote-conf-list li {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.quote-conf-list strong { color: #475569; font-size: 12px; }
.quote-conf-list span { color: #0f172a; font-weight: 600; font-size: 14px; }
.quote-conf-note {
    font-size: 12px;
    color: #64748b;
    line-height: 1.5;
    margin-top: auto;
}
.quote-conf-portal {
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
}
.quote-conf-portal-desc {
    font-size: 13px;
    color: #475569;
    line-height: 1.5;
    margin: 0 0 16px 0;
}
.quote-conf-creds {
    background: #f1f5f9;
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 13px;
}
.quote-conf-creds small { color: #64748b; font-size: 11px; font-weight: 700; display: block; }
.quote-conf-creds strong { color: #0f172a; font-family: monospace; font-size: 14px; }
.quote-conf-creds .quote-conf-pwd { color: var(--qs-brand-primary, #b50e0e); font-weight: 800; }
.quote-conf-pwd-tip {
    font-size: 11px;
    color: #64748b;
    margin: 0 0 16px 0;
}
.quote-conf-btn {
    width: 100%;
    text-align: center;
    margin-top: auto;
    text-decoration: none;
}
.quote-conf-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid var(--qs-line, #e2e8f0);
}
.quote-conf-home-link {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
}
.quote-conf-home-link:hover { color: #0f172a; }
</style>
    </div>
</main>

