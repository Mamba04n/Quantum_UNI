<article class="secure-contact-card service-request-card">
    <div class="service-request-head">
        <span class="commerce-kicker">Solicitud técnica</span>
        <h4>Solicitar servicio técnico</h4>
        <p>Comparte la información del equipo y la condición que presenta. Nuestro equipo revisará el caso y te contactará con próximos pasos.</p>
    </div>
    @if(session('service-success'))<p class="contact-status success">{{ session('service-success') }}</p>@endif
    <form wire:submit="submit" class="secure-contact-form service-request-form">
        <div class="secure-field-row">
            <label>Nombre completo *<input wire:model="name" placeholder="Nombre y apellido"></label>
            <label>Correo electrónico *<input wire:model="email" type="email" placeholder="correo@empresa.com"></label>
        </div>
        <div class="secure-field-row">
            <label>Teléfono<input wire:model="phone" placeholder="+505 0000 0000"></label>
            <label>Ubicación<input wire:model="location" placeholder="Ciudad o zona de operación"></label>
        </div>
        <label>Equipo<input wire:model="machine" placeholder="Máquina, marca y modelo"></label>
        <label>Descripción del caso *<textarea wire:model="description" placeholder="Describe la falla, síntomas, horas de uso o servicio requerido"></textarea></label>
        <label class="file-upload-field">
            <span>Fotografía opcional</span>
            <input wire:model="image" type="file" accept="image/*">
            <em>Sube una foto de la pieza, placa o condición del equipo.</em>
            <small wire:loading wire:target="image">Cargando archivo...</small>
        </label>
        @error('name')<p class="contact-status error">{{ $message }}</p>@enderror
        @error('email')<p class="contact-status error">{{ $message }}</p>@enderror
        @error('description')<p class="contact-status error">{{ $message }}</p>@enderror
        @error('image')<p class="contact-status error">{{ $message }}</p>@enderror
        <button class="secure-submit" type="submit" wire:loading.attr="disabled">
            <span wire:loading.remove>Enviar solicitud de servicio</span>
            <span wire:loading>Enviando solicitud...</span>
        </button>
    </form>
</article>
