<div class="space-y-5 p-6 font-sans sm:p-8">
    @if(session('message'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('message') }}
        </div>
    @endif

    <section>
        <div>
        <a href="{{ route('clients.index') }}" class="mb-3 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a clientes
        </a>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-3">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-sm font-black text-indigo-700">
                {{ mb_strtoupper(mb_substr(trim($client->name), 0, 2, 'UTF-8'), 'UTF-8') }}
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-extrabold tracking-tight text-gray-900">{{ $client->name }}</h1>
                <p class="mt-0.5 truncate text-sm text-gray-500">{{ $client->email ?: 'Correo no registrado' }}@if($client->rfc_ruc) · RUC {{ $client->rfc_ruc }}@endif</p>
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            @can('crm.clients.manage')
                <button type="button" wire:click="editProfile" data-testid="customer-profile-edit-button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Modificar datos
                </button>
            @endcan
            <details class="relative">
                <summary data-testid="customer-profile-actions-button" class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Más acciones
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="absolute right-0 z-30 mt-2 w-52 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                    @can('crm.clients.manage')
                        <button type="button" wire:click="$set('activeTab', 'machines')" class="block w-full px-4 py-2.5 text-left text-xs font-medium text-slate-700 hover:bg-slate-50">Gestionar maquinaria</button>
                        <button type="button" wire:click="toggleClientStatus" data-testid="customer-profile-toggle-status-button" wire:confirm="¿Deseas cambiar el estado operativo de este cliente?" class="block w-full px-4 py-2.5 text-left text-xs font-medium text-slate-700 hover:bg-slate-50">
                            {{ in_array($client->status, ['inactive', 'inactivo'], true) ? 'Reactivar cliente' : 'Desactivar cliente' }}
                        </button>
                    @endcan
                    <button type="button" onclick="window.print()" class="block w-full px-4 py-2.5 text-left text-xs font-medium text-slate-700 hover:bg-slate-50">Imprimir ficha</button>
                    @can('crm.clients.manage')
                        <div class="my-1 border-t border-slate-100"></div>
                        <button type="button" wire:click="deleteClient" wire:confirm="Si el cliente tiene historial se desactivará; si no lo tiene, se eliminará. ¿Deseas continuar?" class="block w-full px-4 py-2.5 text-left text-xs font-medium text-rose-700 hover:bg-rose-50">Eliminar cliente</button>
                    @endcan
                </div>
            </details>
        </div>
        </div>
        </div>

    @if($editingProfile)
        <form wire:submit="saveProfile" class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
                <div><h2 class="text-base font-semibold text-slate-950">Datos legales y comerciales</h2><p class="mt-1 text-sm text-slate-500">Información oficial utilizada en cotizaciones, órdenes, facturas y comunicaciones.</p></div>
                <button type="button" wire:click="cancelProfileEdit" class="text-sm font-medium text-slate-500 hover:text-slate-900">Cancelar</button>
            </div>
            <div class="grid gap-x-5 gap-y-4 p-5 md:grid-cols-2 lg:grid-cols-3">
                <label class="block"><span class="mb-1.5 block text-sm font-medium text-slate-700">Nombre comercial</span><input wire:model="profileName" data-testid="customer-profile-name-input" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm">@error('profileName')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                <label class="block lg:col-span-2"><span class="mb-1.5 block text-sm font-medium text-slate-700">Razón social</span><input wire:model="profileLegalName" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm">@error('profileLegalName')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="mb-1.5 block text-sm font-medium text-slate-700">RUC / NIT</span><input wire:model="profileRuc" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="block"><span class="mb-1.5 block text-sm font-medium text-slate-700">Correo institucional</span><input wire:model="profileEmail" type="email" class="h-11 w-full rounded-lg border-slate-300 text-sm">@error('profileEmail')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="mb-1.5 block text-sm font-medium text-slate-700">Sitio web</span><input wire:model="profileWebsite" type="url" class="h-11 w-full rounded-lg border-slate-300 text-sm" placeholder="https://"></label>
                <label class="block"><span class="mb-1.5 block text-sm font-medium text-slate-700">Teléfono general</span><input wire:model="profilePhone" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="block"><span class="mb-1.5 block text-sm font-medium text-slate-700">Celular corporativo</span><input wire:model="profileMobile" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm"></label>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3"><button type="button" wire:click="cancelProfileEdit" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Cancelar</button><button type="submit" data-testid="customer-profile-save-button" class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Guardar datos</button></div>
        </form>
    @endif

    {{-- KPI CARDS --}}
    <dl class="mt-5 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-gray-100 bg-gray-100 shadow-sm md:grid-cols-4">
        @foreach([
            ['label' => 'Total facturado', 'value' => 'C$ '.number_format($totalFacturado, 2), 'class' => 'text-slate-950'],
            ['label' => 'Saldo pendiente', 'value' => 'C$ '.number_format($totalPendiente, 2), 'class' => $totalPendiente > 0 ? 'text-rose-700' : 'text-slate-950'],
            ['label' => 'Cobros registrados', 'value' => 'C$ '.number_format($totalCobrado, 2), 'class' => 'text-slate-950'],
            ['label' => 'Pedidos activos', 'value' => $pedidosActivos, 'class' => 'text-slate-950'],
        ] as $metric)
            <div class="bg-white px-4 py-3">
                <dt class="text-xs text-slate-500">{{ $metric['label'] }}</dt>
                <dd class="mt-1 text-base font-semibold tabular-nums {{ $metric['class'] }}">{{ $metric['value'] }}</dd>
            </div>
        @endforeach
    </dl>

    {{-- TABS --}}
    <label class="mt-5 block sm:hidden"><span class="sr-only">Sección del cliente</span><select wire:model.live="activeTab" class="h-11 w-full rounded-lg border-slate-300 bg-white text-sm"><option value="profile">Resumen del cliente</option><option value="machines">Maquinaria</option><option value="tickets">Tickets</option><option value="requests">Solicitudes</option><option value="orders">Historial de pedidos</option><option value="receivables">Estados de cuenta</option><option value="invoices">Facturación</option></select></label>
    <nav class="mt-5 hidden w-full grid-cols-7 gap-1 rounded-xl bg-gray-100 p-1 sm:grid" aria-label="Secciones del cliente">
        @foreach(['profile' => 'Perfil técnico', 'machines' => 'Maquinaria', 'tickets' => 'Tickets', 'requests' => 'Solicitudes', 'orders' => 'Historial de pedidos', 'receivables' => 'Estados de cuenta', 'invoices' => 'Facturación'] as $tab => $label)
            <button wire:click="$set('activeTab', '{{ $tab }}')"
                class="rounded-lg px-2 py-2.5 text-center text-xs font-semibold transition-colors {{ $activeTab === $tab ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
            </button>
        @endforeach
    </nav>
    </section>

    {{-- TAB: PERFIL TÉCNICO --}}
    @if($activeTab === 'profile')
    <div class="grid gap-4 lg:grid-cols-12">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-5">
            <div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-900">Identificación de la empresa</h2></div>
            <dl class="grid grid-cols-2 gap-x-4 px-4 py-2">
                @foreach([
                    ['Razón social', $client->legal_name ?: $client->name], ['RUC / NIT', $client->rfc_ruc],
                    ['Correo institucional', $client->email], ['Teléfono general', $client->phone_home ?: $client->phone_mobile],
                    ['Sitio web', $client->website], ['Estado del portal', $client->user?->is_active ? 'Activo' : 'Sin acceso activo'],
                ] as [$label, $value])
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words text-sm font-medium text-slate-800">{{ filled($value) ? $value : 'No indicado' }}</dd></div>
                @endforeach
            </dl>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-3">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <div><h2 class="text-sm font-semibold text-slate-900">Ubicación operativa</h2><p class="mt-0.5 text-xs text-slate-500">{{ $client->country ?: 'Nicaragua' }}@if($client->department) · {{ $client->department }}@endif @if($client->municipality)· {{ $client->municipality }}@endif</p></div>
                @can('crm.clients.manage')
                    <button type="button" wire:click="$toggle('showLocationEditor')" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">{{ $showLocationEditor ? 'Cerrar' : 'Editar ubicación' }}</button>
                @endcan
            </div>
            @if($showLocationEditor)
                <form wire:submit="saveLocation" class="grid grid-cols-2 gap-3 p-4">
                    <label class="block"><span class="mb-1 block text-xs text-slate-600">País</span><input wire:model="country" type="text" class="h-9 w-full rounded-md border-slate-300 text-sm"></label>
                    <label class="block"><span class="mb-1 block text-xs text-slate-600">Departamento</span><select wire:model="department" class="h-9 w-full rounded-md border-slate-300 py-1 text-sm"><option value="">Seleccionar</option>@foreach($nicaraguaDepartments as $departmentOption)<option value="{{ $departmentOption }}">{{ $departmentOption }}</option>@endforeach</select></label>
                    <label class="block"><span class="mb-1 block text-xs text-slate-600">Municipio</span><input wire:model="municipality" type="text" class="h-9 w-full rounded-md border-slate-300 text-sm"></label>
                    <label class="block"><span class="mb-1 block text-xs text-slate-600">Dirección exacta</span><input wire:model="exactAddress" type="text" class="h-9 w-full rounded-md border-slate-300 text-sm"></label>
                    <button class="col-span-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Guardar ubicación</button>
                </form>
            @else
                <div class="px-4 py-4">
                    <p class="text-sm text-slate-700">{{ $client->exact_address ?: $client->address ?: 'No se ha registrado una dirección exacta.' }}</p>
                    @if($client->phone_mobile)<p class="mt-2 text-xs text-slate-500">Contacto para esta ubicación: {{ $client->phone_mobile }}</p>@endif
                </div>
            @endif
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-4">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3"><div><h2 class="text-sm font-semibold text-slate-900">Maquinaria</h2><p class="mt-0.5 text-xs text-slate-500">{{ $machines->count() }} equipos registrados</p></div><button type="button" wire:click="$set('activeTab', 'machines')" class="text-xs font-medium text-slate-700 hover:text-slate-950">Gestionar maquinaria</button></div>
            <div class="divide-y divide-slate-100">
                @forelse($machines->take(3) as $machine)
                    <button type="button" wire:click="selectMachineComponents({{ $machine->id }})" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-slate-50">
                        <div class="min-w-0"><p class="truncate text-sm font-medium text-slate-900">{{ $machine->name }}</p><p class="truncate text-xs text-slate-500">{{ $machine->brand ?: 'Marca no indicada' }} · {{ $machine->model ?: 'Modelo no indicado' }} · {{ $machine->serial_number ?: 'Sin serie' }}</p></div>
                        <span class="shrink-0 text-xs text-slate-500">{{ str($machine->status)->replace('_', ' ')->headline() }}</span>
                    </button>
                @empty
                    <div class="px-4 py-5"><p class="text-sm text-slate-600">No hay maquinaria registrada.</p><button type="button" wire:click="$set('activeTab', 'machines')" class="mt-2 text-xs font-medium text-slate-800 underline">Registrar el primer equipo</button></div>
                @endforelse
            </div>
            @if($client->part_types)<p class="border-t border-slate-200 px-4 py-3 text-xs text-slate-600"><span class="font-medium text-slate-800">Piezas habituales:</span> {{ $client->part_types }}</p>@endif
        </section>
    </div>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div><h2 class="text-sm font-semibold text-slate-900">Personas de contacto</h2><p class="mt-1 text-xs text-slate-500">Empleados y responsables autorizados para coordinar solicitudes, compras y entregas.</p></div>
                @can('crm.clients.manage')<button type="button" wire:click="$toggle('showContactForm')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ $showContactForm ? 'Cerrar' : 'Agregar contacto' }}</button>@endcan
            </div>
            @if($showContactForm)
                <form wire:submit="addContact" class="border-b border-slate-200 bg-slate-50 p-5">
                    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                        <label><span class="mb-1 block text-xs font-medium text-slate-700">Nombre completo</span><input wire:model="contactName" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm">@error('contactName')<span class="text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                        <label><span class="mb-1 block text-xs font-medium text-slate-700">Cargo</span><input wire:model="contactPosition" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs font-medium text-slate-700">Área o departamento</span><input wire:model="contactDepartment" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs font-medium text-slate-700">Correo</span><input wire:model="contactEmail" type="email" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs font-medium text-slate-700">Celular</span><input wire:model="contactMobile" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs font-medium text-slate-700">Teléfono de oficina</span><input wire:model="contactOfficePhone" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs font-medium text-slate-700">WhatsApp</span><input wire:model="contactWhatsapp" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label class="flex items-end gap-2 pb-2"><input wire:model="contactIsPrimary" type="checkbox" class="rounded border-slate-300 text-indigo-600"><span class="text-sm text-slate-700">Contacto principal</span></label>
                        <label class="md:col-span-2 lg:col-span-3"><span class="mb-1 block text-xs font-medium text-slate-700">Observaciones</span><input wire:model="contactNotes" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <div class="flex items-end justify-end"><button class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">Registrar contacto</button></div>
                    </div>
                </form>
            @endif
            <div class="divide-y divide-slate-100">
                @forelse($contacts as $contact)
                    <div class="grid gap-3 px-5 py-4 sm:grid-cols-4 sm:items-center">
                        <div><div class="flex flex-wrap items-center gap-2"><p class="text-sm font-semibold text-slate-900">{{ $contact->name }}</p>@if($contact->is_primary)<span class="text-xs font-medium text-indigo-700">Principal</span>@endif</div><p class="text-xs text-slate-500">{{ $contact->position ?: 'Cargo no indicado' }}@if($contact->department) · {{ $contact->department }}@endif</p></div>
                        <div><p class="text-xs text-slate-500">Correo</p><p class="break-all text-sm text-slate-800">{{ $contact->email ?: 'No registrado' }}</p></div>
                        <div><p class="text-xs text-slate-500">Teléfono</p><p class="text-sm text-slate-800">{{ $contact->mobile ?: $contact->office_phone ?: 'No registrado' }}</p></div>
                        @can('crm.clients.manage')<div class="text-right"><button type="button" wire:click="deleteContact({{ $contact->id }})" wire:confirm="¿Deseas eliminar esta persona de contacto?" class="text-xs font-medium text-slate-500 hover:text-rose-700">Eliminar</button></div>@endcan
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-slate-500">No hay personas de contacto registradas.</p>
                @endforelse
            </div>
        </section>
    @endif

    {{-- TAB: MAQUINARIA --}}
    @if($activeTab === 'machines')
    <div class="space-y-4">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-sm font-semibold text-slate-950">Maquinaria del cliente</h2><p class="mt-1 text-xs text-slate-500">{{ $machines->count() }} {{ $machines->count() === 1 ? 'equipo registrado' : 'equipos registrados' }}.</p></div>
                @can('crm.clients.manage')
                    <button type="button" wire:click="$toggle('showMachineForm')" class="{{ $showMachineForm ? 'border-slate-300 bg-white text-slate-700' : 'border-indigo-600 bg-indigo-600 text-white' }} rounded-lg border px-3 py-2 text-xs font-semibold">
                        {{ $showMachineForm ? 'Cerrar formulario' : 'Registrar maquinaria' }}
                    </button>
                @endcan
            </div>

            @if($showMachineForm)
                <form wire:submit="addMachine" class="border-b border-slate-200 bg-slate-50/60 p-5">
                    <div class="grid gap-3 md:grid-cols-4">
                        <label class="md:col-span-2"><span class="mb-1 block text-xs text-slate-600">Tipo de maquinaria</span><input wire:model="machineType" type="text" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm" placeholder="Ej. Excavadora">@error('machineType')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                        <label><span class="mb-1 block text-xs text-slate-600">Marca</span><input wire:model="machineBrand" type="text" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Modelo</span><input wire:model="machineModel" type="text" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Año</span><input wire:model="machineYear" type="number" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Número de serie</span><input wire:model="machineSerial" type="text" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Código interno</span><input wire:model="machineInternalCode" type="text" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Estado</span><select wire:model="machineStatus" class="h-10 w-full rounded-lg border-slate-300 bg-white py-1 text-sm"><option value="activa">Activa</option><option value="en_mantenimiento">En mantenimiento</option><option value="fuera_de_servicio">Fuera de servicio</option></select></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Horómetro</span><input wire:model="machineHourmeter" type="number" min="0" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Fecha de lectura</span><input wire:model="machineHourmeterDate" type="date" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label class="md:col-span-2"><span class="mb-1 block text-xs text-slate-600">Observaciones</span><input wire:model="machineNotes" type="text" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Imagen opcional</span><input wire:model="machineImage" type="file" accept="image/*" class="block h-10 w-full rounded-lg border border-slate-300 bg-white text-xs file:mr-3 file:h-full file:border-0 file:border-r file:border-slate-200 file:bg-slate-50 file:px-3 file:text-xs file:font-medium">@error('machineImage')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                    </div>
                    <div class="mt-4 flex justify-end"><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Guardar maquinaria</button></div>
                </form>
            @endif

            <div class="divide-y divide-slate-100">
                @forelse($machines as $machine)
                    <button type="button" wire:click="selectMachineComponents({{ $machine->id }})" class="grid w-full gap-3 px-5 py-4 text-left transition-colors hover:bg-slate-50 sm:grid-cols-[minmax(0,1.6fr)_repeat(3,minmax(0,.7fr))_auto] sm:items-center">
                        <div class="flex min-w-0 items-center gap-3">
                            @if($machine->image_path)<img src="{{ route('private.client-machine-image', $machine) }}" class="h-10 w-10 rounded-lg object-cover" alt="">@else<div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-500">{{ mb_strtoupper(mb_substr($machine->machine_type ?: 'EQ', 0, 2)) }}</div>@endif
                            <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ $machine->name }}</p><p class="truncate text-xs text-slate-500">{{ $machine->machine_type ?: 'Tipo no indicado' }} · {{ $machine->brand ?: 'Sin marca' }} {{ $machine->model }}</p></div>
                        </div>
                        <div><p class="text-xs text-slate-400">Serie</p><p class="truncate text-xs font-medium text-slate-700">{{ $machine->serial_number ?: 'No indicada' }}</p></div>
                        <div><p class="text-xs text-slate-400">Horómetro</p><p class="text-xs font-medium tabular-nums text-slate-700">{{ $machine->hourmeter !== null ? number_format($machine->hourmeter) : 'Sin lectura' }}</p></div>
                        <div><p class="text-xs text-slate-400">Componentes</p><p class="text-xs font-medium text-slate-700">{{ $machine->components->count() }}</p></div>
                        <span class="text-xs font-medium {{ $machine->status === 'activa' ? 'text-emerald-700' : ($machine->status === 'en_mantenimiento' ? 'text-amber-700' : 'text-rose-700') }}">{{ str($machine->status)->replace('_', ' ')->headline() }}</span>
                    </button>
                @empty
                    <div class="px-5 py-10 text-center"><h3 class="text-sm font-semibold text-slate-800">No hay maquinaria registrada</h3><p class="mt-1 text-xs text-slate-500">Registra el primer equipo para gestionar componentes y mantenimiento.</p>@can('crm.clients.manage')<button type="button" wire:click="$set('showMachineForm', true)" class="mt-4 text-xs font-semibold text-indigo-700 hover:text-indigo-900">Registrar maquinaria</button>@endcan</div>
                @endforelse
            </div>
        </section>

        @if($selectedMachine)
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-sm font-semibold text-slate-950">Componentes y vida útil</h2><p class="mt-1 text-xs text-slate-500">Equipo seleccionado: {{ $selectedMachine->name }}. Las alertas se calculan con el uso registrado y la vida útil estimada.</p></div>
                <div class="flex items-center gap-2">
                    <select wire:model.live="selectedMachineForComponents" class="h-9 rounded-lg border-slate-300 py-1 text-xs">
                        @foreach($machines as $machineOption)<option value="{{ $machineOption->id }}">{{ $machineOption->name }}</option>@endforeach
                    </select>
                    @can('crm.clients.manage')<button type="button" wire:click="$toggle('showComponentForm')" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">{{ $showComponentForm ? 'Cerrar' : 'Agregar componente' }}</button>@endcan
                </div>
            </div>

            @if($showComponentForm)
                <form wire:submit="addComponent" class="border-b border-slate-200 bg-slate-50/60 p-5">
                    <div class="grid gap-3 md:grid-cols-4">
                        <label><span class="mb-1 block text-xs text-slate-600">Componente</span><input wire:model="componentName" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm">@error('componentName')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                        <label><span class="mb-1 block text-xs text-slate-600">Número de parte</span><input wire:model="componentPartNumber" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm">@error('componentPartNumber')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                        <label><span class="mb-1 block text-xs text-slate-600">Serie</span><input wire:model="componentSerialNumber" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Marca</span><input wire:model="componentBrand" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Modelo</span><input wire:model="componentModel" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Cantidad instalada</span><input wire:model="componentQuantity" type="number" min="1" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Unidad de desgaste</span><select wire:model.live="componentCounterType" class="h-10 w-full rounded-lg border-slate-300 bg-white py-1 text-sm">@foreach($counterTypes as $counterKey => $counterLabel)<option value="{{ $counterKey }}">{{ $counterLabel }}</option>@endforeach</select></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Fecha de instalación</span><input wire:model="componentInstallationDate" type="date" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Lectura al instalar</span><input wire:model="componentLastReplacementHourmeter" type="number" min="0" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Uso actual</span><input wire:model="componentCurrentUsage" type="number" min="0" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Vida útil estimada</span><input wire:model="componentEstimatedLife" type="number" min="1" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                        <label><span class="mb-1 block text-xs text-slate-600">Fotografía opcional</span><input wire:model="componentImage" type="file" accept="image/*" class="block h-10 w-full rounded-lg border border-slate-300 bg-white text-xs file:mr-2 file:h-full file:border-0 file:border-r file:border-slate-200 file:bg-slate-50 file:px-2 file:text-xs">@error('componentImage')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                        <label class="md:col-span-4"><span class="mb-1 block text-xs text-slate-600">Observaciones</span><input wire:model="componentNotes" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm"></label>
                    </div>
                    @if($componentCounterType === 'horas')<p class="mt-3 text-xs text-slate-500">El uso se calculará tomando como referencia el horómetro actual del equipo: {{ number_format($selectedMachine->hourmeter ?? 0) }} horas.</p>@endif
                    <div class="mt-4 flex justify-end"><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Guardar componente</button></div>
                </form>
            @endif

            <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Componente</th>
                                    <th class="px-4 py-3">PN / Serie</th>
                                    <th class="px-4 py-3">Uso</th>
                                    <th class="px-4 py-3">Vida</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @forelse($selectedMachine->components->sortBy('priority') as $component)
                                    @php
                                        $badge = match($component->status) {
                                            'vencido' => 'text-rose-700',
                                            'critico' => 'text-rose-700',
                                            'atencion_proxima' => 'text-amber-700',
                                            'reemplazado' => 'text-slate-500',
                                            'fuera_de_servicio' => 'text-slate-600',
                                            default => 'text-emerald-700',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-gray-900">{{ $component->component_name }}</p>
                                            <p class="text-xs text-gray-500">{{ $component->component_brand }} {{ $component->component_model }}</p>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-gray-600">{{ $component->part_number }}<br><span class="text-gray-400">{{ $component->serial_number ?: '-' }}</span></td>
                                        <td class="px-4 py-3">
                                            <p>{{ $component->current_usage }} / {{ $component->estimated_life }} {{ $counterTypes[$component->counter_type] ?? $component->counter_type }}</p>
                                            @if($component->counter_type === 'horas')
                                                <p class="text-xs text-gray-400">Reemplazo: {{ $component->last_replacement_hourmeter ?? 0 }} · Equipo: {{ $component->machine?->hourmeter ?? 0 }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 min-w-40">
                                            <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                                                <div class="h-full {{ $component->consumed_percentage >= 90 ? 'bg-rose-500' : ($component->consumed_percentage >= 70 ? 'bg-amber-400' : 'bg-emerald-500') }}" style="width: {{ min(100, (float) $component->consumed_percentage) }}%"></div>
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500">{{ number_format($component->consumed_percentage, 1) }}% · residual {{ $component->residual_life }}</p>
                                        </td>
                                        <td class="px-4 py-3"><span class="text-xs font-medium {{ $badge }}">{{ str($component->status)->replace('_', ' ')->headline() }}</span></td>
                                        <td class="px-4 py-3 text-right">
                                            <button wire:click="showComponent({{ $component->id }})" class="text-xs font-medium text-slate-600 hover:text-slate-900">Ver detalle</button>
                                            @if(in_array($component->status, ['atencion_proxima','critico','vencido']))
                                                <button wire:click="generateComponentQuote({{ $component->id }})" class="ml-3 text-xs font-medium text-indigo-700 hover:text-indigo-900">Cotizar</button>
                                                <button wire:click="createComponentTicket({{ $component->id }})" class="ml-3 text-xs font-medium text-amber-700 hover:text-amber-900">Crear ticket</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">Sin componentes registrados para esta maquina.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
            </div>

                    @if($selectedComponent)
                        <div class="grid gap-4 border-t border-slate-200 bg-slate-50/60 p-5 lg:grid-cols-2">
                            <form wire:submit="updateComponentUsage" class="space-y-3">
                                <h4 class="text-sm font-semibold text-slate-900">Actualizar uso: {{ $selectedComponent->component_name }}</h4>
                                <p class="text-xs text-gray-500">{{ $selectedComponent->counter_type === 'horas' ? 'Ingresa la lectura actual del horometro del equipo.' : 'Ingresa el uso acumulado actual del componente.' }}</p>
                                <input wire:model="newComponentUsage" type="number" min="0" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm" placeholder="{{ $selectedComponent->counter_type === 'horas' ? 'Horómetro actual del equipo' : 'Nuevo uso' }}">
                                @if($selectedComponent->counter_type === 'horas')
                                    <div class="rounded-xl bg-white border border-gray-100 p-3 text-xs text-gray-500">
                                        <p class="font-bold text-gray-700">Calculo del componente</p>
                                        <p>Horometro de instalacion: {{ $selectedComponent->last_replacement_hourmeter ?? 0 }} horas</p>
                                        <p>Uso actual del componente: {{ $selectedComponent->current_usage }} horas</p>
                                        <p>Vida residual: {{ $selectedComponent->residual_life }} horas</p>
                                    </div>
                                @endif
                                <textarea wire:model="usageUpdateNotes" rows="2" class="w-full rounded-lg border-slate-300 bg-white text-sm" placeholder="Observación de lectura"></textarea>
                                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Guardar lectura</button>
                            </form>
                            <div class="max-h-72 overflow-y-auto rounded-lg border border-slate-200 bg-white p-4">
                                <h4 class="mb-3 text-sm font-semibold text-slate-900">Historial de uso</h4>
                                <div class="space-y-3">
                                    @forelse($selectedComponent->usageLogs as $log)
                                        <div class="border-l-2 border-slate-200 pl-3">
                                            <p class="text-xs font-bold text-gray-800">{{ $log->previous_usage }} -> {{ $log->new_usage }} {{ $log->counter_type }}</p>
                                            <p class="text-[10px] text-gray-500">{{ $log->previous_status ?: '-' }} -> {{ $log->new_status }} · {{ $log->created_at->format('d/m/Y H:i') }}</p>
                                            @if($log->notes)<p class="text-[10px] text-gray-500 mt-1">{{ $log->notes }}</p>@endif
                                        </div>
                                    @empty
                                        <p class="text-xs text-gray-400">Sin historial.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @endif
        </section>
        @endif
    </div>
    @endif

    {{-- TAB: TICKETS --}}
    @if($activeTab === 'tickets')
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3 text-left font-medium">Ticket</th><th class="px-4 py-3 text-left font-medium">Estado</th><th class="px-4 py-3 text-left font-medium">Maquinaria</th><th class="px-4 py-3 text-left font-medium">Fecha</th></tr></thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($tickets as $ticket)
                    <tr class="hover:bg-slate-50"><td class="px-4 py-3 font-semibold">{{ $ticket->code }} · {{ $ticket->subject }}</td><td class="px-4 py-3">{{ str($ticket->status)->replace('_', ' ')->headline() }}</td><td class="px-4 py-3">{{ $ticket->clientMachine?->name ?? '-' }}</td><td class="px-4 py-3">{{ $ticket->created_at->format('d/m/Y') }}</td></tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-10 text-center text-gray-400">Sin tickets relacionados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- TAB: SOLICITUDES --}}
    @if($activeTab === 'requests')
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3 text-left font-medium">Referencia</th><th class="px-4 py-3 text-left font-medium">Pieza</th><th class="px-4 py-3 text-left font-medium">Origen</th><th class="px-4 py-3 text-left font-medium">Estado</th></tr></thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($quotationRequests as $request)
                    <tr class="hover:bg-slate-50"><td class="px-4 py-3 font-mono font-semibold">{{ $request->reference }}</td><td class="px-4 py-3">{{ $request->part_code ?: '-' }}</td><td class="px-4 py-3">{{ str($request->source_channel ?: 'sistema')->headline() }}</td><td class="px-4 py-3">{{ str($request->status)->replace('_', ' ')->headline() }}</td></tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-10 text-center text-gray-400">Sin solicitudes relacionadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- TAB: PEDIDOS --}}
    @if($activeTab === 'orders')
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Referencia</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Ítems</th>
                        <th class="px-4 py-3 text-right">Total C$</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                        <th class="px-4 py-3 text-center">PDF</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($orders as $order)
                        @php
                            $statusMap = [
                                'cotizacion'           => ['Cotización',    'bg-gray-100 text-gray-700'],
                                'solicitado_proveedor' => ['Pedido Ext.',   'bg-slate-100 text-slate-700'],
                                'en_transito_aduanas'  => ['En Tránsito',  'bg-zinc-100 text-zinc-700'],
                                'en_inventario_local'  => ['En Bodega',    'bg-stone-100 text-stone-700'],
                                'entregado'            => ['Entregado',    'bg-teal-100 text-teal-700'],
                                'cancelado'            => ['Cancelado',    'bg-rose-100 text-rose-700'],
                            ];
                            $s = $statusMap[$order->status] ?? [$order->status, 'bg-gray-100 text-gray-600'];
                        @endphp
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-4 py-2.5 font-bold text-gray-800">{{ $order->reference }}</td>
                            <td class="px-4 py-2.5 text-gray-600">{{ \Carbon\Carbon::parse($order->date)->format('d/m/Y') }}</td>
                            <td class="px-4 py-2.5 text-gray-500">{{ $order->items->count() }} artículo(s)</td>
                            <td class="px-4 py-2.5 text-right font-bold text-gray-900">C$ {{ number_format($order->total_amount, 2) }}</td>
                            <td class="px-4 py-2.5 text-center">
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold {{ $s[1] }}">{{ $s[0] }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <a href="{{ route('sales.orders.pdf', $order->id) }}" target="_blank" class="text-[10px] font-bold text-slate-600 hover:text-slate-900 underline">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 text-sm">Sin pedidos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- TAB: CXC --}}
    @if($activeTab === 'receivables')
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-3">N° Fact.</th>
                        <th class="px-4 py-3">Concepto</th>
                        <th class="px-4 py-3">Condición</th>
                        <th class="px-4 py-3">Fec. Fact.</th>
                        <th class="px-4 py-3 text-right">Importe C$</th>
                        <th class="px-4 py-3 text-right">Pago</th>
                        <th class="px-4 py-3 text-right">Saldo</th>
                        <th class="px-4 py-3">Vencimiento</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($receivables as $rec)
                        @php $saldo = $rec->total_amount - $rec->paid_amount; @endphp
                        <tr class="hover:bg-gray-50/50 transition-colors {{ $rec->status === 'pagado' ? 'opacity-60' : '' }}">
                            <td class="px-4 py-2.5 font-mono text-gray-600">{{ $rec->invoice_number ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-700 max-w-[180px] truncate">{{ $rec->concept }}</td>
                            <td class="px-4 py-2.5"><span class="text-[10px] bg-slate-100 text-slate-700 font-bold px-1.5 py-0.5 rounded">{{ $rec->condition ?: '—' }}</span></td>
                            <td class="px-4 py-2.5 text-gray-600">{{ $rec->invoice_date ? \Carbon\Carbon::parse($rec->invoice_date)->format('d/m/Y') : '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-bold text-gray-900">C$ {{ number_format($rec->total_amount, 2) }}</td>
                            <td class="px-4 py-2.5 text-right text-teal-700">{{ $rec->paid_amount > 0 ? 'C$ '.number_format($rec->paid_amount, 2) : '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold tabular-nums {{ $saldo > 0 ? 'text-gray-900' : 'text-emerald-700' }}">C$ {{ number_format($saldo, 2) }}</td>
                            <td class="px-4 py-2.5 text-gray-600">{{ $rec->due_date ? \Carbon\Carbon::parse($rec->due_date)->format('d/m/Y') : '—' }}</td>
                            <td class="px-4 py-2.5 text-center">
                                @if($rec->status === 'pagado')
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">Pagado</span>
                                @elseif($rec->status === 'parcial')
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Parcial</span>
                                @else
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Pendiente</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-6 py-10 text-center text-gray-400 text-sm">Sin cuentas por cobrar.</td></tr>
                    @endforelse
                </tbody>
                @if($receivables->count() > 0)
                <tfoot class="bg-gray-50 border-t border-gray-100">
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-xs font-bold text-gray-600">{{ $receivables->count() }} registros</td>
                        <td class="px-4 py-2 text-right text-xs font-semibold tabular-nums text-gray-900">C$ {{ number_format($receivables->sum('total_amount'), 2) }}</td>
                        <td class="px-4 py-2 text-right text-xs font-semibold tabular-nums text-emerald-700">C$ {{ number_format($receivables->sum('paid_amount'), 2) }}</td>
                        <td class="px-4 py-2 text-right text-xs font-semibold tabular-nums text-rose-700">C$ {{ number_format($receivables->sum(fn($r) => $r->total_amount - $r->paid_amount), 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    @endif

    {{-- TAB: FACTURAS --}}
    @if($activeTab === 'invoices')
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-3">N° Factura</th>
                        <th class="px-4 py-3">Descripción</th>
                        <th class="px-4 py-3">Cotización</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3 text-right">Monto C$</th>
                        <th class="px-4 py-3 text-right">Neto</th>
                        <th class="px-4 py-3">Vencimiento</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                        <th class="px-4 py-3 text-center">PDF</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($invoices as $inv)
                        <tr class="hover:bg-gray-50/50 transition-colors {{ $inv->status === 'Pagado' ? 'opacity-60' : '' }}">
                            <td class="px-4 py-2.5 font-mono font-bold text-gray-700">{{ $inv->invoice_number ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-700 max-w-[180px] truncate">{{ $inv->description }}</td>
                            <td class="px-4 py-2.5 text-gray-500">{{ $inv->cotizacion_ref ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-600">{{ $inv->date ? $inv->date->format('d/m/Y') : '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-bold text-gray-900">C$ {{ number_format($inv->amount_nio, 2) }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-700">C$ {{ number_format($inv->amount_nio - $inv->retention_amount, 2) }}</td>
                            <td class="px-4 py-2.5 text-gray-600">{{ $inv->due_date ? $inv->due_date->format('d/m/Y') : '—' }}</td>
                            <td class="px-4 py-2.5 text-center">
                                @if($inv->status === 'Pagado')
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">Pagado</span>
                                @elseif($inv->status === 'Parcial')
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Parcial</span>
                                @else
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Pendiente</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <a href="{{ route('finance.invoices.pdf', $inv->id) }}" target="_blank" class="text-[10px] font-bold text-slate-600 hover:text-slate-900 underline">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-6 py-10 text-center text-gray-400 text-sm">Sin facturas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
