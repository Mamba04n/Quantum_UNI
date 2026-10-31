<main class="contact-page">
    <section class="location-contact-section">
        <div class="container">
            <div class="location-contact-shell">
                <div class="location-contact-head">
                    <div>
                        <span class="commerce-kicker">Contacto directo</span>
                        <h1>Vis&iacute;tanos o env&iacute;anos tu requerimiento t&eacute;cnico.</h1>
                    </div>
                    <div>
                        <p>Estamos listos para ayudarte con refacciones, mantenimiento y soporte t&eacute;cnico. Comparte el modelo, placa, foto de la pieza o n&uacute;mero de parte para validar compatibilidad y disponibilidad.</p>
                        <div class="location-points">
                            <span class="location-point"><i class="fas fa-location-dot"></i> Cobertura regional agr&iacute;cola e industrial</span>
                            <span class="location-point"><i class="fas fa-clock"></i> Lunes a S&aacute;bado &middot; 8:00 a 18:00</span>
                            <span class="location-point"><i class="fas fa-headset"></i> Atenci&oacute;n comercial y t&eacute;cnica</span>
                        </div>
                    </div>
                </div>
                <div class="location-contact-grid">
                    <article class="office-map-card">
                        <span class="map-live-dot">Oficina activa</span>
                        <div data-map-consent-placeholder><strong>Consulta nuestra ubicación</strong><p>Para ver el mapa, autoriza la carga del servicio externo de Google Maps.</p><button type="button" data-cookie-open>Configurar mapas y privacidad</button></div><iframe class="office-map-frame" loading="lazy" data-consent-src="https://www.google.com/maps/embed?pb=!1m17!1m12!1m3!1d3901.031894646502!2d-86.25195692493783!3d12.109969088131827!2m3!1f0!2f0!3f0!3m2!1i1024!1i768!4f13.1!3m2!1m1!2zMTLCsDA2JzM1LjkiTiA4NsKwMTQnNTcuOCJX!5e0!3m2!1sen!2sni" title="Ubicaci&oacute;n Quantum" hidden></iframe>
                        <div class="office-map-meta">
                            <strong>Quantum Soluciones &middot; Oficina principal</strong>
                            <span>Colonia Centro Am&eacute;rica, Managua &middot; Lunes a S&aacute;bado de 8:00 a 18:00</span>
                        </div>
                    </article>
                    <article class="secure-contact-card">
                        <h4>Env&iacute;anos tu solicitud</h4>
                        <p>Completa el formulario y nuestro equipo responder&aacute; con atenci&oacute;n personalizada.</p>
                        @if(session('success'))<p class="contact-status success">{{ session('success') }}</p>@endif
                        <form wire:submit="submit" class="secure-contact-form">
                            <div class="secure-field-row">
                                <input wire:model="name" type="text" placeholder="Nombre completo">
                                <input wire:model="email" type="email" placeholder="Correo electr&oacute;nico">
                            </div>
                            @error('name')<p class="contact-status error">{{ $message }}</p>@enderror
                            @error('email')<p class="contact-status error">{{ $message }}</p>@enderror
                            <input wire:model="phone" type="tel" placeholder="Tel&eacute;fono (opcional)">
                            <textarea wire:model="message" placeholder="Cu&eacute;ntanos qu&eacute; necesitas y en qu&eacute; podemos ayudarte"></textarea>
                            @error('message')<p class="contact-status error">{{ $message }}</p>@enderror
                            <button class="secure-submit" type="submit">Enviar consulta segura</button>
                        </form>
                    </article>
                </div>
            </div>
        </div>
    </section>
</main>
