<div class="p-6 font-sans sm:p-8">

    {{-- ── Header ── --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Directorio de clientes</h1>
            <p class="mt-0.5 text-sm text-gray-500">Consulta clientes, facturación y saldos pendientes desde un mismo lugar.</p>
        </div>
        <button type="button" wire:click="openCreate" data-testid="customer-create-button"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
            Agregar cliente
        </button>
    </div>

    {{-- ── Alerta ── --}}
    @if(session('message'))
        <div class="mb-5 flex items-center gap-3 p-3.5 text-sm text-teal-800 bg-teal-50 border border-teal-200 rounded-xl font-medium">
            <svg class="w-4 h-4 text-teal-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- ── Buscador + Contador ── --}}
    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div class="relative w-full max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input wire:model.live.debounce.500ms="search" data-testid="customer-search-input"
                   type="search" aria-label="Buscar cliente, contacto o RUC"
                   placeholder="Buscar cliente, contacto o RUC..."
                   class="w-full rounded-xl border-transparent bg-gray-50 py-2 pl-9 pr-4 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200">
        </div>
        <span class="text-xs font-medium text-slate-500">
            {{ $clients->count() }} {{ $clients->count() === 1 ? 'cliente' : 'clientes' }}
        </span>
    </div>

    {{-- ── Tabla Principal ── --}}
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="w-[46%] px-5 py-3.5 font-semibold">Cliente</th>
                        <th class="w-[22%] px-5 py-3.5 font-semibold text-right">Facturación</th>
                        <th class="w-[20%] px-5 py-3.5 font-semibold text-right">Saldo pendiente</th>
                        <th class="w-[12%] px-5 py-3.5 font-semibold text-right"><span class="sr-only">Abrir perfil</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($clients as $client)
                        @php
                            $total_facturado = $client->invoices->sum(function ($invoice) {
                                if ($invoice->amount_nio !== null) {
                                    return (float) $invoice->amount_nio;
                                }

                                return strtoupper((string) $invoice->currency) === 'NIO'
                                    ? (float) $invoice->total
                                    : 0;
                            });
                            $total_pagado    = $client->accountReceivables->sum('paid_amount');
                            $total_cxc        = $client->accountReceivables->sum('total_amount');
                            $deuda            = $total_cxc - $total_pagado;
                        @endphp
                        <tr data-testid="customer-row-{{ $client->id }}"
                            role="link" tabindex="0"
                            onclick="window.location.href='{{ route('clients.profile', $client->id) }}'"
                            onkeydown="if(event.key === 'Enter' || event.key === ' '){ event.preventDefault(); window.location.href='{{ route('clients.profile', $client->id) }}'; }"
                            class="group cursor-pointer transition-colors hover:bg-indigo-50/35 focus:bg-indigo-50/50 focus:outline-none {{ in_array($client->status, ['inactive', 'inactivo'], true) ? 'opacity-55' : '' }}">
                            {{-- Avatar + Nombre --}}
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-black uppercase text-slate-600">
                                        {{ mb_strtoupper(mb_substr(trim($client->name), 0, 2, 'UTF-8'), 'UTF-8') }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-gray-900 transition-colors group-hover:text-indigo-700">{{ $client->name }}</p>
                                        <p class="mt-0.5 truncate text-xs text-gray-500">
                                            {{ $client->email ?: ($client->contact_info ?: 'Sin contacto registrado') }}
                                            @if($client->rfc_ruc)<span class="text-gray-300"> · </span>RUC {{ $client->rfc_ruc }}@endif
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-gray-400">{{ $client->invoices->count() }} {{ $client->invoices->count() === 1 ? 'factura' : 'facturas' }} · {{ $client->accountReceivables->count() }} registros CXC</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Total Facturado --}}
                            <td class="px-5 py-3.5 text-right">
                                <span class="font-semibold tabular-nums text-gray-800">C$ {{ number_format($total_facturado, 2) }}</span>
                            </td>

                            {{-- Saldo CXC --}}
                            <td class="px-5 py-3.5 text-right">
                                @if($deuda > 0)
                                    <span class="text-xs font-semibold tabular-nums text-rose-700">
                                        C$ {{ number_format($deuda, 2) }}
                                    </span>
                                @else
                                    <span class="text-xs font-medium text-emerald-700">
                                        Al día
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-3.5 text-right">
                                <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-500 group-hover:text-indigo-700">
                                    {{ in_array($client->status, ['inactive', 'inactivo'], true) ? 'Inactivo' : 'Ver perfil' }}
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg>
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">
                                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-indigo-50 mb-4">
                                    <svg class="w-7 h-7 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </div>
                                <h3 class="text-sm font-bold text-gray-700">
                                    {{ $search ? 'Sin resultados para "' . $search . '"' : 'Sin clientes registrados' }}
                                </h3>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $search ? 'Intenta con otro término de búsqueda.' : 'Presiona «Agregar Cliente» para comenzar.' }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4">
        {{ $clients->links() }}
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         MODAL — AGREGAR / EDITAR CLIENTE
    ══════════════════════════════════════════════════════════════════ --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" wire:click="closeModal"></div>

            {{-- Panel --}}
            <div class="relative z-10 w-full max-w-3xl rounded-xl border border-slate-200 bg-white shadow-xl"
                 x-data x-trap="true">

                {{-- Header del modal --}}
                <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-slate-950">
                                {{ $editingId ? 'Editar cliente' : 'Nuevo cliente' }}
                            </h3>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $editingId ? 'Modifica los datos del cliente.' : 'Completa los datos para registrar el cliente.' }}
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeModal" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Formulario --}}
                <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    {{-- Nombre --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-slate-600">
                            Nombre o razón social <span class="text-rose-500">*</span>
                        </label>
                        <input wire:model="name"
                               data-testid="customer-name-input"
                               type="text"
                               placeholder="Ej: Corporación Zeta S.A."
                               autofocus
                               class="w-full rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-indigo-100">
                        @error('name')
                            <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        {{-- Email --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-slate-600">Correo principal</label>
                            <input wire:model="email"
                                   data-testid="customer-email-input"
                                   type="email"
                                   placeholder="cliente@gmail.com"
                                   class="w-full rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-indigo-100">
                            @error('email')
                                <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- RUC --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-slate-600">RUC / NIT</label>
                            <input wire:model="rfc_ruc"
                                   type="text"
                                   placeholder="J123456789"
                                   class="w-full rounded-lg border-slate-300 bg-white px-3 py-2.5 font-mono text-sm focus:border-indigo-400 focus:ring-indigo-100">
                            @error('rfc_ruc')
                                <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        {{-- Teléfono Móvil --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Teléfono Celular</label>
                            <input wire:model="phone_mobile"
                                   data-testid="customer-phone-input"
                                   type="text"
                                   placeholder="+505 8888-0000"
                                   class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 transition-all">
                            @error('phone_mobile')
                                <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Teléfono Casa --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5">Teléfono Convencional</label>
                            <input wire:model="phone_home"
                                   type="text"
                                   placeholder="2222-0000"
                                   class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 transition-all">
                            @error('phone_home')
                                <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    {{-- Otros datos de contacto (Legacy) --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Otros datos de contacto</label>
                        <input wire:model="contact_info"
                               type="text"
                               placeholder="Referencias adicionales..."
                               class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 transition-all">
                    </div>

                    <div class="pt-4 border-t border-gray-100">
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/70 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-900">Acceso al portal del cliente</h4>
                                    <p class="mt-1 text-xs leading-5 text-slate-600">El correo principal será su usuario. Puedes crear el acceso, cambiar la contraseña o suspenderlo sin borrar su historial.</p>
                                </div>
                                <label class="inline-flex shrink-0 items-center gap-2 text-xs font-medium text-slate-700">
                                    <input wire:model.live="portal_access" type="checkbox" class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                    Acceso activo
                                </label>
                            </div>
                            @if($portal_access)
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-bold text-indigo-900 mb-1.5">{{ $portalAccountExists ? 'Nueva contraseña (opcional)' : 'Contraseña inicial' }}</label>
                                        <input wire:model="portal_password" type="password" autocomplete="new-password" class="w-full rounded-xl border-indigo-200 bg-white py-2.5 px-3 text-sm" placeholder="Mínimo 10 caracteres">
                                        @error('portal_password') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-indigo-900 mb-1.5">Confirmar contraseña</label>
                                        <input wire:model="portal_password_confirmation" type="password" autocomplete="new-password" class="w-full rounded-xl border-indigo-200 bg-white py-2.5 px-3 text-sm">
                                    </div>
                                </div>
                                <p class="mt-3 text-[10px] font-bold text-indigo-700">Portal: {{ route('customer.portal') }} · La cuenta queda verificada y con rol exclusivo de cliente.</p>
                            @endif
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100">
                        <h4 class="mb-4 text-sm font-semibold text-slate-900">Ubicación operativa</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5">Pais</label>
                                <input wire:model="country" type="text" placeholder="Nicaragua" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5">Departamento</label>
                                <select wire:model="department" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 transition-all">
                                    <option value="">Seleccionar...</option>
                                    @foreach($nicaraguaDepartments as $departmentOption)
                                        <option value="{{ $departmentOption }}">{{ $departmentOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5">Municipio</label>
                                <input wire:model="municipality" type="text" placeholder="Ej: Managua" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5">Direccion exacta</label>
                                <input wire:model="exact_address" data-testid="customer-address-input" type="text" placeholder="Barrio, referencia, km..." class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 transition-all">
                            </div>
                        </div>
                        <div class="mt-4 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3">
                            <p class="text-[11px] font-semibold text-amber-800">La maquinaria detallada se gestiona en Flota Tecnica o en la pestana Maquinaria del perfil, evitando duplicar datos.</p>
                        </div>
                    </div>

                    <div class="hidden">
                        <h4 class="text-[10px] font-black uppercase text-gray-400 tracking-widest mb-4">Perfil Técnico y Maquinaria</h4>

                        <div class="space-y-4">
                            {{-- Maquinaria --}}
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5">Maquinaria que posee</label>
                                <textarea wire:model="machinery"
                                          placeholder="Ej: Excavadoras CAT 320D, Camiones Komatsu..."
                                          rows="2"
                                          class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 transition-all"></textarea>
                            </div>

                            {{-- Piezas --}}
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5">Tipos de piezas que utiliza</label>
                                <textarea wire:model="part_types"
                                          placeholder="Ej: Filtros, Rodamientos, Sistemas hidráulicos..."
                                          rows="2"
                                          class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 transition-all"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer del modal --}}
                <div class="px-6 pb-6 flex gap-3">
                    <button type="button" wire:click="closeModal"
                            class="flex-1 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-50 transition-colors">
                        Cancelar
                    </button>
                    <button type="button" wire:click="save"
                            data-testid="customer-save-button"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="flex-[2] py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-semibold rounded-xl transition-colors flex items-center justify-center gap-2">
                        <svg wire:loading.remove wire:target="save" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Guardar Cambios' : 'Registrar Cliente' }}</span>
                        <span wire:loading wire:target="save">Guardando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════
         MODAL — GESTIÓN DE FLOTA / MAQUINARIA
    ══════════════════════════════════════════════════════════════════ --}}
    @if($showFleetModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeFleet"></div>

            <div class="relative z-10 bg-white rounded-3xl shadow-2xl w-full max-w-4xl border border-gray-100 overflow-hidden flex flex-col max-h-[90vh]">
                {{-- Header --}}
                <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between bg-white">
                    <div>
                        <h3 class="text-xl font-black text-gray-900">Flota de Maquinaria</h3>
                        <p class="text-sm text-indigo-600 font-bold uppercase tracking-widest mt-0.5">{{ $clientName }}</p>
                    </div>
                    <button type="button" wire:click="closeFleet" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-hidden flex flex-col md:flex-row">
                    {{-- Formulario de Alta (Izquierda) --}}
                    <div class="w-full md:w-96 bg-gray-50/50 p-8 border-r border-gray-100 overflow-y-auto">
                        <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-6">Nueva Máquina / Equipo</h4>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Tipo de maquinaria *</label>
                                <input wire:model="machine_type" type="text" placeholder="Excavadora, camion, generador..." class="w-full bg-white border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-100 transition-all">
                                @error('machine_type') <p class="text-[10px] text-rose-600 font-bold mt-1">{{ $message }}</p> @enderror
                                <input wire:model="machine_name" type="text" placeholder="Nombre interno opcional" class="mt-2 w-full bg-white border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-100 transition-all">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Marca</label>
                                    <input wire:model="machine_brand" type="text" class="w-full bg-white border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-100 transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Modelo</label>
                                    <input wire:model="machine_model" type="text" class="w-full bg-white border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-100 transition-all">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">N° Serie / VIN</label>
                                <input wire:model="machine_serial" type="text" class="w-full bg-white border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-100 transition-all uppercase">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Detalles Técnicos / Piezas</label>
                                <textarea wire:model="machine_details" rows="4" class="w-full bg-white border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-100 transition-all" placeholder="Piezas críticas, filtros, etc..."></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Codigo interno</label>
                                    <input wire:model="machine_internal_code" type="text" class="w-full bg-white border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-100 transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Ano</label>
                                    <input wire:model="machine_year" type="number" class="w-full bg-white border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-100 transition-all">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Horometro</label>
                                    <input wire:model="machine_hourmeter" type="number" min="0" class="w-full bg-white border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-100 transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Fecha lectura</label>
                                    <input wire:model="machine_hourmeter_date" type="date" class="w-full bg-white border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-100 transition-all">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Estado</label>
                                <select wire:model="machine_status" class="w-full bg-white border-gray-200 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-100 transition-all">
                                    <option value="activa">Activa</option>
                                    <option value="en_mantenimiento">En mantenimiento</option>
                                    <option value="fuera_de_servicio">Fuera de servicio</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Imagen</label>
                                <input wire:model="machine_image" type="file" class="w-full bg-white border border-gray-200 rounded-xl py-2 px-3 text-xs">
                                @error('machine_image') <p class="text-[10px] text-rose-600 font-bold mt-1">{{ $message }}</p> @enderror
                            </div>

                            <button type="button" wire:click="addMachine" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-3 rounded-2xl shadow-lg shadow-indigo-100 transition-all text-xs">
                                AGREGAR A LA FLOTA
                            </button>
                        </div>
                    </div>

                    {{-- Listado de Flota (Derecha) --}}
                    <div class="flex-1 p-8 overflow-y-auto">
                        <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-6">Inventario Técnico Actual</h4>

                        @if(count($machines) > 0)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach($machines as $m)
                                    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all group relative">
                                        <button type="button" wire:click="deleteMachine({{ $m['id'] }})" class="absolute top-4 right-4 text-gray-300 hover:text-rose-500 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                        @if(!empty($m['image_path']))
                                            <img src="{{ route('private.client-machine-image', $m['id']) }}" class="w-full h-28 object-cover rounded-xl mb-4 border border-gray-100" loading="lazy" alt="Maquinaria">
                                        @endif

                                        <div class="flex items-center gap-3 mb-4">
                                            <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            </div>
                                            <div>
                                                <h5 class="text-sm font-black text-gray-900 uppercase">{{ $m['name'] }}</h5>
                                                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-tight">{{ $m['machine_type'] ?? 'Equipo' }} · {{ $m['brand'] }} {{ $m['model'] }}</p>
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <div class="flex justify-between items-center text-[10px]">
                                                <span class="text-gray-400 font-bold uppercase">Serie/VIN:</span>
                                                <span class="text-slate-800 font-black tracking-widest">{{ $m['serial_number'] ?: 'N/A' }}</span>
                                            </div>
                                            <div class="flex justify-between items-center text-[10px]">
                                                <span class="text-gray-400 font-bold uppercase">Codigo:</span>
                                                <span class="text-slate-700 font-bold">{{ $m['internal_code'] ?? 'N/A' }}</span>
                                            </div>
                                            <div class="flex justify-between items-center text-[10px]">
                                                <span class="text-gray-400 font-bold uppercase">Horometro:</span>
                                                <span class="text-slate-700 font-bold">{{ $m['hourmeter'] ?? 'N/A' }} @if(!empty($m['hourmeter_date'])) · {{ \Carbon\Carbon::parse($m['hourmeter_date'])->format('d/m/Y') }} @endif</span>
                                            </div>
                                            <div class="flex justify-between items-center text-[10px]">
                                                <span class="text-gray-400 font-bold uppercase">Estado:</span>
                                                <span class="text-slate-700 font-bold">{{ str_replace('_', ' ', $m['status'] ?? 'activa') }}</span>
                                            </div>
                                            <div class="mt-3 pt-3 border-t border-gray-50">
                                                <p class="text-[9px] font-black text-indigo-400 uppercase mb-1">Notas Técnicas / Piezas</p>
                                                <p class="text-[11px] text-gray-600 leading-relaxed italic">
                                                    {{ $m['technical_details'] ?: 'No hay detalles técnicos registrados.' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center py-20 text-center opacity-40">
                                <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                <p class="text-sm font-bold uppercase tracking-widest text-gray-400">Sin maquinaria registrada</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
            <div class="relative z-10 mx-4 w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
                <h3 class="mb-2 text-base font-semibold text-slate-950">Eliminar o desactivar cliente</h3>
                <p class="text-sm text-gray-500 mb-5">
                    Si el cliente tiene solicitudes, ordenes, tickets, tracker, facturas o maquinaria, se desactivara para conservar el historial.
                </p>
                <div class="flex gap-3">
                    <button type="button" wire:click="$set('showDeleteModal', false)" class="flex-1 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-50 transition-colors">
                        Cancelar
                    </button>
                    <button type="button" wire:click="destroyClient" class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-xl transition-colors">
                        Continuar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
