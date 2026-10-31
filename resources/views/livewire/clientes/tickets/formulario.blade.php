<div class="cp-panel-heading"><div class="cp-title-icon"><button type="button" wire:click="backToList" class="cp-icon-button" aria-label="Volver a mis tickets"><x-portal.icon name="back" /></button><h2>Nuevo ticket</h2></div><span class="cp-caption">Los campos con * son obligatorios</span></div>
<form wire:submit="createTicket" class="cp-ticket-form">
    <div class="cp-form-intro"><x-portal.icon name="support" /><div><h3>Cuéntanos cómo podemos ayudarte</h3><p>Incluye el modelo del equipo o la referencia de tu pedido cuando corresponda.</p></div></div>
    <div class="cp-form-grid">
        <div class="cp-field"><label for="ticket-type">Tipo de solicitud *</label><select id="ticket-type" wire:model.live="type">@foreach($typeLabels as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach</select>@error('type')<p class="cp-field-error" role="alert">{{ $message }}</p>@enderror</div>
        <div class="cp-field"><label for="ticket-priority">Prioridad *</label><select id="ticket-priority" wire:model="priority">@foreach($priorityLabels as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach</select>@error('priority')<p class="cp-field-error" role="alert">{{ $message }}</p>@enderror</div>
    </div>
    @if($type === \App\Models\Ticket::TYPE_PURCHASE)
        <fieldset class="cp-purchase-fields"><legend><x-portal.icon name="machine" />Información de la pieza o equipo</legend><div class="cp-form-grid">
            @foreach(['machinery' => ['Nombre de pieza o maquinaria *', 'text'], 'machineBrand' => ['Marca', 'text'], 'machineModel' => ['Modelo', 'text'], 'equipmentSerial' => ['Serie del equipo', 'text'], 'partCode' => ['Código de pieza *', 'text'], 'requestQuantity' => ['Cantidad *', 'number']] as $field => [$label, $inputType])
                <div class="cp-field"><label for="ticket-{{ $field }}">{{ $label }}</label><input id="ticket-{{ $field }}" type="{{ $inputType }}" wire:model="{{ $field }}" @if($inputType === 'number') min="1" @endif>@error($field)<p class="cp-field-error" role="alert">{{ $message }}</p>@enderror</div>
            @endforeach
        </div></fieldset>
    @endif
    <div class="cp-field"><label for="ticket-subject">Asunto *</label><input id="ticket-subject" type="text" wire:model="subject" placeholder="Resume tu consulta" maxlength="255">@error('subject')<p class="cp-field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="cp-field"><label for="ticket-description">Descripción *</label><textarea id="ticket-description" wire:model="description" rows="5" placeholder="Describe lo que necesitas y los datos que nos ayuden a revisarlo." maxlength="5000"></textarea>@error('description')<p class="cp-field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="cp-field"><label for="ticket-attachment">Archivo adjunto <span>(opcional)</span></label><input id="ticket-attachment" type="file" wire:model="attachment" aria-describedby="ticket-file-help"><p id="ticket-file-help" class="cp-caption">Imágenes, PDF o documentos. Máximo 5 MB.</p><p class="cp-caption" wire:loading wire:target="attachment">Subiendo archivo…</p>@error('attachment')<p class="cp-field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="cp-form-actions"><button type="button" wire:click="backToList" class="cp-button">Cancelar</button><button type="submit" class="cp-button cp-button--primary" wire:loading.attr="disabled" wire:target="createTicket,attachment"><x-portal.icon name="plus" /><span wire:loading.remove wire:target="createTicket">Crear ticket</span><span wire:loading wire:target="createTicket">Creando ticket…</span></button></div>
</form>
