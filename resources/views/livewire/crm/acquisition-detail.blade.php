@php($readOnly = $selected && ($selected->discarded_at || $selected->opportunity))
@if($selected)
<div class="mb-5 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600"><span>{{ config('acquisition.stages')[$selected->stage] }}</span><span>Creado {{ $selected->created_at->format('d/m/Y H:i') }}</span><span>Responsable: {{ $selected->responsible?->name }}</span></div>
@if($selected->discarded_at)<p class="mb-5 text-sm text-gray-600">Descartado: {{ $selected->discard_reason }}</p>@endif
@if($selected->opportunity)<section class="mb-6 border-b border-gray-100 pb-5"><h3 class="font-semibold">Oportunidad comercial #{{ $selected->opportunity->id }}</h3><p class="mt-2 text-sm">{{ $selected->opportunity->title }} · {{ $selected->opportunity->created_at->format('d/m/Y H:i') }}</p><p class="mt-1 text-sm text-gray-600">{{ $selected->opportunity->need }}</p><p class="mt-1 text-xs text-gray-500">Origen: prospecto #{{ $selected->id }} → cliente potencial → oportunidad. La captación se conserva en modo consulta.</p>@if(auth()->user()->hasPermissionTo('crm.clients.manage'))<a href="{{ route('clients.profile', $selected->entity_id) }}" class="mt-2 inline-block text-sm font-semibold text-slate-700 underline">Ver cliente vinculado →</a>@endif</section>@endif
@endif
<form wire:submit="save">
<fieldset @disabled($readOnly || (!$creating && !auth()->user()->hasPermissionTo('prospects.edit')))>
    <h3 class="mb-3 text-sm font-semibold">Información general</h3>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-acquisition-field label="Empresa/persona *" model="form.name" required maxlength="255"/>
        <label class="text-sm font-medium text-gray-700">Fuente *<select wire:model="form.source" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><option value="">Seleccionar fuente</option>@foreach(config('acquisition.sources') as $value)<option>{{ $value }}</option>@endforeach</select></label>
        <x-acquisition-field label="Ciudad" model="form.city"/>
        <x-acquisition-field label="Sector" model="form.sector"/>
        <x-acquisition-field label="Teléfono" model="form.phone" type="tel"/>
        <x-acquisition-field label="Correo" model="form.email" type="email"/>
        <label class="text-sm font-medium text-gray-700">Responsable *<select wire:model="form.responsible_id" @disabled(!auth()->user()->hasPermissionTo('prospects.reassign')) class="mt-1 block w-full rounded-xl border-gray-200 text-sm">@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select></label>
        <label class="min-w-0 text-sm font-medium text-gray-700">Campaña (opcional)<select wire:model="form.campaign_id" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><option value="">Sin campaña</option>@foreach($campaigns as $entry)<option value="{{ $entry->id }}">{{ $entry->name }}</option>@endforeach</select></label>
        @if(!$creating)<x-acquisition-field label="RUC" model="form.ruc"/><x-acquisition-field label="Sitio web" model="form.website" type="url"/>@endif
    </div>
    <label class="mt-4 block text-sm font-medium text-gray-700">{{ $creating ? 'Comentario inicial' : 'Comentario' }}<textarea wire:model="form.comment" rows="2" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></textarea></label>
    <section class="mt-6 border-t border-gray-100 pt-5"><div class="mb-3 flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Contactos y estructura organizativa</h3><button type="button" wire:click="addContact" class="shrink-0 text-sm font-semibold text-slate-700">+ Agregar contacto</button></div>
    @if(!$creating)
        <dl class="mb-4 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">@foreach(config('acquisition.buying_functions') as $function => $functionLabel)<div><dt class="font-medium text-gray-700">Quién {{ mb_strtolower($functionLabel) }}</dt><dd class="mt-0.5 text-gray-500">{{ collect($form['contacts'] ?? [])->filter(fn ($c) => in_array($function, $c['buying_functions'] ?? []))->pluck('name')->implode(', ') ?: 'Sin identificar' }}</dd></div>@endforeach</dl>
    @endif
    @foreach($form['contacts'] ?? [] as $index => $contact)
        <div wire:key="contact-{{ $index }}" class="mb-5 grid gap-3 border-b border-gray-100 pb-4 last:border-0 sm:grid-cols-2">
            <x-acquisition-field label="Nombre *" model="form.contacts.{{ $index }}.name"/>
            <x-acquisition-field label="Cargo" model="form.contacts.{{ $index }}.position"/>
            <x-acquisition-field label="Teléfono" model="form.contacts.{{ $index }}.phone" type="tel"/>
            <x-acquisition-field label="Correo" model="form.contacts.{{ $index }}.email" type="email"/>
            @if(!$creating)
                <label class="text-sm font-medium text-gray-700">Rol comercial<select wire:model="form.contacts.{{ $index }}.commercial_role" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><option value="">Sin identificar</option>@foreach(config('acquisition.contact_roles') as $role)<option>{{ $role }}</option>@endforeach</select></label>
                <fieldset class="text-sm"><legend class="font-medium text-gray-700">Participación en la compra</legend><div class="mt-2 flex flex-wrap gap-3">@foreach(config('acquisition.buying_functions') as $function => $functionLabel)<label class="inline-flex items-center gap-1.5 text-xs text-gray-600"><input type="checkbox" wire:model="form.contacts.{{ $index }}.buying_functions" value="{{ $function }}" class="rounded border-gray-300 text-slate-800">{{ $functionLabel }}</label>@endforeach</div></fieldset>
            @endif
            <button type="button" wire:click="removeContact({{ $index }})" class="justify-self-start text-xs text-red-800">Quitar contacto</button>
        </div>
    @endforeach
    </section>
    @if(!$creating)
        @include('livewire.crm.acquisition-commercial-context')
    @endif
</fieldset>
@if(!$readOnly && ($creating || auth()->user()->hasPermissionTo('prospects.edit')))
    <section class="mt-5 border-t border-gray-100 pt-4">
        <button type="button" wire:click="$refresh" class="text-sm font-semibold text-slate-700 underline">Revisar posibles duplicados</button>
        @if($matches)<ul class="mt-2 space-y-1 text-sm text-amber-800">@foreach($matches as $match)<li>{{ $match['name'] }} · {{ $match['exact'] ? 'Coincidencia de identificación/contacto' : 'Nombre coincidente' }} @if($match['type'] === 'prospect' && $match['id'])<button type="button" wire:click="open({{ $match['id'] }})" class="underline">Abrir prospecto</button>@endif</li>@endforeach</ul>@endif
        <label class="mt-3 flex gap-2 text-xs text-gray-600"><input type="checkbox" wire:model="duplicateAck" class="rounded border-gray-300 text-slate-800">Revisé las coincidencias por nombre y confirmé que es otro registro. Los identificadores exactos no pueden duplicarse.</label>
        @if(auth()->user()->hasPermissionTo('crm.clients.manage'))
        <label class="mt-3 block text-sm text-gray-600">Vincular cliente existente (opcional)<input type="search" wire:model.live.debounce.400ms="clientSearch" placeholder="Buscar cliente por nombre o RUC..." class="mt-1 w-full rounded-xl border-gray-200 text-sm"></label>
        <select wire:model="form.entity_id" aria-label="Cliente existente" class="mt-2 w-full rounded-xl border-gray-200 text-sm"><option value="">Sin cliente vinculado</option>@if($selected?->entity)<option value="{{ $selected->entity_id }}">{{ $selected->entity->name }}</option>@endif @foreach($clients as $client)<option value="{{ $client->id }}">{{ $client->name }} {{ $client->rfc_ruc }}</option>@endforeach @foreach($matches as $match)@if($match['type'] === 'client' && $match['id'])<option value="{{ $match['id'] }}">Cliente coincidente #{{ $match['id'] }}</option>@endif @endforeach</select>
        @endif
    </section>
    <div class="mt-5 flex justify-end"><button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-900 disabled:opacity-50"><span wire:loading.remove wire:target="save">{{ $creating ? 'Crear prospecto' : 'Guardar cambios' }}</span><span wire:loading wire:target="save">Guardando...</span></button></div>
@endif
</form>
@if($selected && !$readOnly)
    @if(auth()->user()->hasPermissionTo('prospects.move'))<form wire:submit="moveSelected" class="mt-6 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-5"><label class="text-sm">Etapa<select wire:model="targetStage" class="mt-1 block rounded-xl border-gray-200 text-sm">@foreach(config('acquisition.stages') as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label><button type="submit" wire:loading.attr="disabled" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold">Cambiar etapa</button></form>@endif
    @if(auth()->user()->hasPermissionTo('prospects.edit'))
    <details class="mt-6 border-t border-gray-100 pt-5"><summary class="cursor-pointer text-sm font-semibold">Registrar contacto real</summary><form wire:submit="recordContact" class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="text-sm">Medio<select wire:model="interaction.channel" class="mt-1 block w-full rounded-xl border-gray-200 text-sm">@foreach(config('acquisition.channels') as $channel)<option>{{ $channel }}</option>@endforeach</select></label>
        <x-acquisition-field label="Fecha de contacto *" model="interaction.date" type="datetime-local" required/>
        <x-acquisition-field label="Persona contactada *" model="interaction.person" required/>
        <x-acquisition-field label="Cargo" model="interaction.position"/>
        <label class="text-sm sm:col-span-2">Resumen de la interacción *<textarea wire:model="interaction.summary" required rows="2" maxlength="3000" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></textarea></label>
        <x-acquisition-field label="Resultado breve *" model="interaction.result" required/>
        <x-acquisition-field label="Próxima acción *" model="interaction.next_action" required/>
        <x-acquisition-field label="Próximo contacto *" model="interaction.next_contact_at" type="date" required/>
        <button type="submit" wire:loading.attr="disabled" class="self-end rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Registrar contacto</button>
    </form></details>
    @endif
    @if($selected->stage === 'calificados' && auth()->user()->hasPermissionTo('prospects.convert'))
    <section class="mt-6 border-t border-gray-100 pt-5"><h3 class="text-sm font-semibold">Crear oportunidad comercial</h3><p class="mt-1 text-sm text-gray-500">Se conservará el prospecto y se vinculará un cliente. No se generan cotizaciones, pedidos ni pagos.</p>
        @if(auth()->user()->hasPermissionTo('crm.clients.manage'))<label class="mt-3 block text-sm">Buscar cliente existente<input type="search" wire:model.live.debounce.400ms="clientSearch" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></label><select wire:model="convertEntityId" aria-label="Cliente para la oportunidad" class="mt-2 w-full rounded-xl border-gray-200 text-sm"><option value="">Crear cliente tras revisar duplicados</option>@if($selected->entity)<option value="{{ $selected->entity_id }}">{{ $selected->entity->name }}</option>@endif @foreach($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach @foreach($matches as $match)@if($match['type'] === 'client' && $match['id'])<option value="{{ $match['id'] }}">Cliente coincidente #{{ $match['id'] }}</option>@endif @endforeach</select>@endif
        <button type="button" wire:click="convert" wire:confirm="¿Crear la oportunidad y finalizar la captación de este prospecto?" wire:loading.attr="disabled" class="mt-3 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Crear oportunidad comercial</button>
    </section>@endif
    @if(auth()->user()->hasPermissionTo('prospects.discard'))<details class="mt-6 border-t border-gray-100 pt-5"><summary class="cursor-pointer text-sm text-red-800">Descartar prospecto</summary><form wire:submit="discard" class="mt-3 space-y-3"><label class="block text-sm">Motivo *<select wire:model="discardReason" required class="mt-1 w-full rounded-xl border-gray-200 text-sm"><option value="">Seleccionar motivo</option>@foreach(config('acquisition.discard_reasons') as $reason)<option>{{ $reason }}</option>@endforeach</select></label><x-acquisition-field label="Detalle (obligatorio para Otro)" model="discardNotes"/><button type="submit" wire:confirm="¿Descartar este prospecto conservando su historial?" wire:loading.attr="disabled" class="rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-800">Confirmar descarte</button></form></details>@endif
@endif
@if($selected)
<section class="mt-6 border-t border-gray-100 pt-5"><h3 class="mb-3 text-sm font-semibold">Historial de contactos y cambios</h3>
    <p class="text-sm text-gray-600">Último contacto: {{ $selected->last_contact_at?->format('d/m/Y H:i') ?? 'Sin registrar' }} {{ $selected->last_contact_channel }}</p>
    @if($selected->last_result)<p class="mt-1 text-sm text-gray-600">Resultado: {{ $selected->last_result }}</p>@endif
    <ol class="mt-4 divide-y divide-gray-100">@foreach($history as $event)<li class="py-3 text-sm"><span class="text-xs text-gray-500">{{ $event->action === 'contact' && !empty($event->metadata['date']) ? \Carbon\Carbon::parse($event->metadata['date'])->format('d/m/Y H:i') : $event->created_at->format('d/m/Y H:i') }} · {{ $event->user?->name }}</span><p class="mt-1 font-medium">{{ $event->description }}</p>@if($event->action === 'contact')@if(!empty($event->metadata['position']))<p class="mt-1 text-xs text-gray-500">Cargo: {{ $event->metadata['position'] }}</p>@endif @if(!empty($event->metadata['summary']))<p class="mt-1 text-gray-600">Resumen: {{ $event->metadata['summary'] }}</p>@endif <p class="mt-1 text-gray-600">Resultado: {{ $event->metadata['result'] ?? '' }}</p><p class="mt-1 text-xs text-gray-500">Próxima acción: {{ $event->metadata['next_action'] ?? '' }}</p><p class="mt-1 text-xs text-gray-500">Próximo seguimiento: {{ $event->metadata['next_contact_at'] ?? '' }}</p>@endif</li>@endforeach</ol>
    @if($historyCount > $history->count())<button type="button" wire:click="moreHistory" wire:loading.attr="disabled" class="mt-3 text-sm font-semibold text-slate-700 underline">Ver contactos y eventos anteriores ({{ $historyCount - $history->count() }})</button>@endif
</section>
@endif
