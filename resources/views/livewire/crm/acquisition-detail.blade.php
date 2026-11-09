@php
    $readOnly = $selected && ($selected->discarded_at || $selected->opportunity);
    $canEdit = $creating || auth()->user()->hasPermissionTo('prospects.edit');
    $canMove = auth()->user()->hasPermissionTo('prospects.move') && !$readOnly;
@endphp

<div class="space-y-6">
    {{-- Breadcrumb Navigation --}}
    <nav class="flex items-center gap-2 text-xs font-medium text-gray-400" aria-label="Ubicación">
        <a href="{{ route('workspace') }}" class="hover:text-gray-700 transition-colors">Inicio</a>
        <span>/</span>
        <a href="{{ route('workspace', 'ventas') }}" class="hover:text-gray-700 transition-colors">Ventas</a>
        <span>/</span>
        <button type="button" wire:click="close" class="hover:text-gray-700 transition-colors">Captación</button>
        <span>/</span>
        <span class="text-[#b50e0e] font-semibold truncate max-w-xs" aria-current="page">
            {{ $creating ? 'Nuevo prospecto' : $selected->name }}
        </span>
    </nav>

    {{-- Encabezado Principal de la Vista Completa --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-2xs">
        <div class="flex items-start gap-4">
            <button
                type="button"
                wire:click="close"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 hover:border-gray-300 transition-colors"
                title="Regresar al tablero Kanban"
            >
                <x-workspace-icon name="arrow" class="h-4 w-4 rotate-180 text-gray-500" />
                <span>Volver al tablero</span>
            </button>

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight text-gray-900 truncate">
                        {{ $creating ? 'Nuevo prospecto comercial' : $selected->name }}
                    </h1>
                    @if($selected)
                        @php
                            [$stageBadge, $stageText] = match($selected->stage) {
                                'contactados' => ['bg-amber-50 text-amber-800 border-amber-200', 'Contactados'],
                                'interesados' => ['bg-blue-50 text-blue-800 border-blue-200', 'Interesados'],
                                'calificados' => ['bg-rose-50 text-[#b50e0e] border-rose-200', 'Calificados'],
                                default => ['bg-slate-100 text-slate-800 border-slate-200', 'Prospectos'],
                            };
                        @endphp
                        <span class="inline-flex items-center rounded-lg border px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider {{ $stageBadge }}">
                            {{ config('acquisition.stages')[$selected->stage] ?? $stageText }}
                        </span>
                        @if($selected->classification)
                            <span class="inline-flex items-center rounded-lg border border-rose-100 bg-rose-50 px-2 py-0.5 text-xs font-bold text-[#b50e0e]" title="Clasificación">
                                Clase {{ $selected->classification }}
                            </span>
                        @endif
                    @endif
                </div>

                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
                    @if($creating)
                        <span>Captura los datos iniciales de la empresa y contactos para comenzar la prospección.</span>
                    @else
                        <span>Creado: <strong class="text-gray-700">{{ $selected->created_at->format('d/m/Y H:i') }}</strong></span>
                        <span>&bull;</span>
                        <span>Responsable: <strong class="text-gray-700">{{ $selected->responsible?->name ?? 'Sin asignar' }}</strong></span>
                        @if($selected->source)
                            <span>&bull;</span>
                            <span>Fuente: <strong class="text-gray-700">{{ $selected->source }}</strong></span>
                        @endif
                        @if($selected->campaign)
                            <span>&bull;</span>
                            <span>Campaña: <strong class="text-[#b50e0e]">{{ $selected->campaign->name }}</strong></span>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Acciones del Encabezado --}}
        <div class="flex flex-wrap items-center gap-2.5 self-start lg:self-center">
            @if(!$readOnly && $canEdit)
                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#b50e0e] hover:bg-[#9a0101] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors disabled:opacity-50"
                >
                    <x-workspace-icon name="check" class="h-4 w-4" />
                    <span wire:loading.remove wire:target="save">{{ $creating ? 'Crear prospecto' : 'Guardar cambios' }}</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Notificaciones de Estado y Errores --}}
    @if($notice)
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 flex items-center gap-2 shadow-2xs">
            <x-workspace-icon name="check" class="h-4 w-4 text-emerald-600 shrink-0" />
            <span>{{ $notice }}</span>
        </div>
    @endif

    @if($errors->any())
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 space-y-1 shadow-2xs">
            <p class="font-bold flex items-center gap-1.5 mb-1 text-rose-900">
                <x-workspace-icon name="close" class="h-4 w-4 text-rose-600 shrink-0" />
                <span>Por favor revisa los siguientes requerimientos:</span>
            </p>
            @foreach($errors->all() as $error)
                <p class="pl-5">&bull; {{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Banners Contextuales según Etapa Actual del Prospecto --}}
    @if($selected && !$readOnly)
        @if($selected->stage === 'prospectos')
            <div class="rounded-2xl border border-amber-200/90 bg-amber-50/70 p-4 text-sm text-amber-900 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800 font-bold">1</span>
                    <div>
                        <p class="font-bold text-amber-950">Etapa actual: Prospecto inicial</p>
                        <p class="text-xs text-amber-800 mt-0.5">
                            Para avanzar a <strong>Contactados</strong> en el Kanban, es requisito registrar al menos una persona de contacto y completar el <strong>Acercamiento comercial</strong>.
                        </p>
                    </div>
                </div>
                <a href="#seccion-acercamiento" class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-amber-700 hover:bg-amber-800 px-3.5 py-2 text-xs font-bold text-white transition-colors">
                    <span>Ir a Registrar acercamiento</span>
                    <x-workspace-icon name="arrow" class="h-3 w-3" />
                </a>
            </div>
        @elseif($selected->stage === 'contactados')
            <div class="rounded-2xl border border-blue-200/90 bg-blue-50/70 p-4 text-sm text-blue-900 shadow-2xs flex items-center gap-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-800 font-bold">2</span>
                <div>
                    <p class="font-bold text-blue-950">Etapa actual: Contactados (Primer acercamiento realizado)</p>
                    <p class="text-xs text-blue-800 mt-0.5">
                        Para avanzar a <strong>Interesados</strong>, registra en la sección inferior la necesidad detectada del cliente y los productos o servicios de interés.
                    </p>
                </div>
            </div>
        @elseif($selected->stage === 'interesados')
            <div class="rounded-2xl border border-purple-200/90 bg-purple-50/70 p-4 text-sm text-purple-900 shadow-2xs flex items-center gap-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-purple-100 text-purple-800 font-bold">3</span>
                <div>
                    <p class="font-bold text-purple-950">Etapa actual: Interesados (Existe necesidad comercial)</p>
                    <p class="text-xs text-purple-800 mt-0.5">
                        Para calificar el prospecto a <strong>Calificados</strong>, asigna una clasificación comercial (A, B o C), completa al menos un dato de contexto y valora un criterio de compra.
                    </p>
                </div>
            </div>
        @elseif($selected->stage === 'calificados')
            <div class="rounded-2xl border border-rose-200/90 bg-rose-50/70 p-4 text-sm text-rose-900 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-[#b50e0e] font-bold">4</span>
                    <div>
                        <p class="font-bold text-rose-950">Etapa actual: Prospecto Calificado</p>
                        <p class="text-xs text-rose-800 mt-0.5">
                            El prospecto cumple todos los criterios. Puedes convertirlo en una <strong>Oportunidad Comercial</strong> para vincularlo formalmente como cliente en el sistema.
                        </p>
                    </div>
                </div>
                @if(auth()->user()->hasPermissionTo('prospects.convert'))
                    <button
                        type="button"
                        wire:click="convert"
                        wire:confirm="¿Crear la oportunidad comercial y finalizar la captación de este prospecto?"
                        wire:loading.attr="disabled"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-[#b50e0e] hover:bg-[#9a0101] px-3.5 py-2 text-xs font-bold text-white shadow-sm transition-colors"
                    >
                        <x-workspace-icon name="check" class="h-3.5 w-3.5" />
                        <span>Convertir en Oportunidad</span>
                    </button>
                @endif
            </div>
        @endif
    @endif

    @if($selected && $selected->discarded_at)
        <div class="rounded-2xl border border-gray-200 bg-gray-100/80 p-4 text-sm text-gray-700 shadow-2xs">
            <p class="font-bold text-gray-900">Prospecto Descartado</p>
            <p class="text-xs text-gray-600 mt-0.5">Motivo: {{ $selected->discard_reason }}</p>
            <p class="text-xs text-gray-500 mt-1">El prospecto se conserva en modo solo lectura para trazabilidad y auditoría comercial.</p>
        </div>
    @endif

    @if($selected && $selected->opportunity)
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 text-sm text-emerald-900 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="font-bold text-emerald-950 flex items-center gap-2">
                    <x-workspace-icon name="check" class="h-4 w-4 text-emerald-600" />
                    <span>Convertido en Oportunidad Comercial #{{ $selected->opportunity->id }}</span>
                </p>
                <p class="text-xs text-emerald-800 mt-0.5">
                    {{ $selected->opportunity->title }} &bull; Creada {{ $selected->opportunity->created_at->format('d/m/Y H:i') }}
                </p>
                <p class="text-xs text-emerald-700 mt-1 font-medium">{{ $selected->opportunity->need }}</p>
            </div>
            @if(auth()->user()->hasPermissionTo('crm.clients.manage') && $selected->entity_id)
                <a href="{{ route('clients.profile', $selected->entity_id) }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-slate-800 hover:bg-slate-900 px-3.5 py-2 text-xs font-semibold text-white transition-colors">
                    <span>Ver cliente vinculado</span>
                    <x-workspace-icon name="arrow" class="h-3 w-3" />
                </a>
            @endif
        </div>
    @endif

    {{-- LAYOUT PRINCIPAL: 2 COLUMNAS (CONTENIDO PRINCIPAL + PANEL LATERAL) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- COLUMNA PRINCIPAL (2/3 DEL ANCHO) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- SECCIÓN 1: DATOS GENERALES DE LA EMPRESA --}}
            <section class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-gray-900 flex items-center gap-2">
                        <x-workspace-icon name="target" class="h-4 w-4 text-[#b50e0e]" />
                        <span>Información de la empresa / cliente</span>
                    </h2>
                    <span class="text-xs text-gray-400">* Campos requeridos</span>
                </div>

                <fieldset @disabled($readOnly || !$canEdit) class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-acquisition-field label="Empresa o persona comercial *" model="form.name" required maxlength="255" placeholder="Nombre comercial de la empresa" />

                        <label class="block text-sm font-medium text-gray-700">
                            <span>Fuente del prospecto *</span>
                            <select wire:model="form.source" required class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/60 py-2 px-3 text-sm text-gray-900 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20 transition-all">
                                <option value="">Seleccionar fuente</option>
                                @foreach(config('acquisition.sources') as $value)
                                    <option value="{{ $value }}">{{ $value }}</option>
                                @endforeach
                            </select>
                        </label>

                        <x-acquisition-field label="RUC / Identificación fiscal" model="form.ruc" placeholder="Ej: J0310000000000" />
                        <x-acquisition-field label="Sitio web" model="form.website" type="url" placeholder="https://empresa.com" />
                        <x-acquisition-field label="Ciudad / Ubicación" model="form.city" placeholder="Ej: Managua, Chinandega..." />
                        <x-acquisition-field label="Sector económico" model="form.sector" placeholder="Ej: Construcción, Minería, Transporte..." />
                        <x-acquisition-field label="Teléfono principal" model="form.phone" type="tel" placeholder="Ej: +505 8888 8888" />
                        <x-acquisition-field label="Correo principal" model="form.email" type="email" placeholder="contacto@empresa.com" />

                        <label class="block text-sm font-medium text-gray-700">
                            <span>Responsable comercial *</span>
                            <select wire:model="form.responsible_id" @disabled(!auth()->user()->hasPermissionTo('prospects.reassign')) class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/60 py-2 px-3 text-sm text-gray-900 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20 transition-all">
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block text-sm font-medium text-gray-700">
                            <span>Campaña comercial (opcional)</span>
                            <select wire:model="form.campaign_id" class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/60 py-2 px-3 text-sm text-gray-900 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20 transition-all">
                                <option value="">Sin campaña asignada</option>
                                @foreach($campaigns as $entry)
                                    <option value="{{ $entry->id }}">{{ $entry->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <label class="block text-sm font-medium text-gray-700 pt-2">
                        <span>{{ $creating ? 'Comentario inicial' : 'Notas generales del prospecto' }}</span>
                        <textarea wire:model="form.comment" rows="2" placeholder="Observaciones de captación, procedencia o antecedentes..." class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/60 p-3 text-sm text-gray-900 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20 transition-all"></textarea>
                    </label>
                </fieldset>
            </section>

            {{-- SECCIÓN 2: PERSONAS DE CONTACTO Y ROLES --}}
            <section class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-gray-900 flex items-center gap-2">
                            <x-workspace-icon name="users" class="h-4 w-4 text-[#b50e0e]" />
                            <span>Personas de contacto</span>
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">Al menos 1 persona con nombre requerida para validar la etapa de Contactados.</p>
                    </div>

                    @if(!$readOnly && $canEdit)
                        <button
                            type="button"
                            wire:click="addContact"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-colors shadow-2xs"
                        >
                            <x-workspace-icon name="plus" class="h-3.5 w-3.5 text-[#b50e0e]" />
                            <span>Agregar contacto</span>
                        </button>
                    @endif
                </div>

                @if(!$creating && !empty($form['contacts']))
                    <dl class="grid gap-3 sm:grid-cols-2 rounded-xl bg-gray-50/70 p-3 border border-gray-100 text-xs">
                        @foreach(config('acquisition.buying_functions') as $function => $functionLabel)
                            <div>
                                <dt class="font-medium text-gray-500">Quién {{ mb_strtolower($functionLabel) }}:</dt>
                                <dd class="mt-0.5 font-bold text-gray-800">
                                    {{ collect($form['contacts'] ?? [])->filter(fn ($c) => in_array($function, $c['buying_functions'] ?? []))->pluck('name')->implode(', ') ?: 'Sin identificar' }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                <div class="space-y-4">
                    @forelse($form['contacts'] ?? [] as $index => $contact)
                        <div wire:key="contact-{{ $index }}" class="rounded-xl border border-gray-200/90 bg-gray-50/40 p-4 space-y-3 relative">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Contacto #{{ $index + 1 }}</span>
                                @if(!$readOnly && $canEdit)
                                    <button
                                        type="button"
                                        wire:click="removeContact({{ $index }})"
                                        class="text-xs font-semibold text-rose-700 hover:text-rose-900 transition-colors"
                                    >
                                        Quitar contacto
                                    </button>
                                @endif
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-acquisition-field label="Nombre completo *" model="form.contacts.{{ $index }}.name" placeholder="Ej: Ing. Juan Pérez" />
                                <x-acquisition-field label="Cargo / Posición" model="form.contacts.{{ $index }}.position" placeholder="Ej: Gerente de Mantenimiento" />
                                <x-acquisition-field label="Teléfono directo" model="form.contacts.{{ $index }}.phone" type="tel" placeholder="Ej: +505 8888 1234" />
                                <x-acquisition-field label="Correo electrónico" model="form.contacts.{{ $index }}.email" type="email" placeholder="juan.perez@empresa.com" />

                                @if(!$creating)
                                    <label class="block text-xs font-medium text-gray-700">
                                        <span>Rol comercial</span>
                                        <select wire:model="form.contacts.{{ $index }}.commercial_role" class="mt-1 block w-full rounded-xl border border-gray-200 bg-white py-1.5 px-3 text-xs text-gray-900 focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20">
                                            <option value="">Sin identificar</option>
                                            @foreach(config('acquisition.contact_roles') as $role)
                                                <option value="{{ $role }}">{{ $role }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <div class="text-xs sm:col-span-2 pt-1">
                                        <span class="block font-medium text-gray-700 mb-1.5">Participación en la decisión de compra</span>
                                        <div class="flex flex-wrap gap-3">
                                            @foreach(config('acquisition.buying_functions') as $function => $functionLabel)
                                                <label class="inline-flex items-center gap-1.5 text-xs text-gray-600 bg-white px-2 py-1 rounded-lg border border-gray-200 cursor-pointer">
                                                    <input type="checkbox" wire:model="form.contacts.{{ $index }}.buying_functions" value="{{ $function }}" class="rounded border-gray-300 text-[#b50e0e] focus:ring-[#b50e0e]/20" />
                                                    <span>{{ $functionLabel }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center p-6 text-center rounded-xl border border-dashed border-gray-200 bg-gray-50/50">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-1.5">
                                <x-workspace-icon name="users" class="h-4 w-4" />
                            </span>
                            <p class="text-xs font-semibold text-gray-600">No has agregado personas de contacto</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Haz clic en "+ Agregar contacto" para registrar a los decisores o encargados.</p>
                            @if(!$readOnly && $canEdit)
                                <button type="button" wire:click="addContact" class="mt-3 rounded-xl bg-slate-800 hover:bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition-colors">
                                    + Agregar primer contacto
                                </button>
                            @endif
                        </div>
                    @endforelse
                </div>
            </section>

            {{-- SECCIÓN 3: REGISTRO DE ACERCAMIENTO COMERCIAL / CONTACTO REAL --}}
            @if($selected && !$readOnly && auth()->user()->hasPermissionTo('prospects.edit'))
                <section id="seccion-acercamiento" class="rounded-2xl border-2 border-amber-300/80 bg-gradient-to-b from-amber-50/50 to-white p-5 sm:p-6 shadow-xs space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-amber-200/80 pb-3">
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-amber-950 flex items-center gap-2">
                                <x-workspace-icon name="phone" class="h-4 w-4 text-amber-700" />
                                <span>Registrar acercamiento / contacto real</span>
                            </h2>
                            <p class="text-xs text-amber-800 mt-0.5">
                                Documenta la interacción sostenida con el prospecto. Es el requisito indispensable para avanzar a <strong>Contactados</strong>.
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-amber-100 border border-amber-300 px-2.5 py-0.5 text-xs font-bold text-amber-900 self-start sm:self-center">
                            Requisito de avance
                        </span>
                    </div>

                    <form wire:submit="recordContact" class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block text-xs font-medium text-gray-700">
                                <span>Medio o canal de contacto *</span>
                                <select wire:model="interaction.channel" required class="mt-1 block w-full rounded-xl border border-gray-200 bg-white py-2 px-3 text-xs font-semibold text-gray-900 focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20">
                                    @foreach(config('acquisition.channels') as $channel)
                                        <option value="{{ $channel }}">{{ $channel }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <x-acquisition-field label="Fecha y hora del contacto *" model="interaction.date" type="datetime-local" required />
                            <x-acquisition-field label="Persona contactada *" model="interaction.person" required placeholder="Nombre de la persona con quien hablaste" />
                            <x-acquisition-field label="Cargo / Área" model="interaction.position" placeholder="Ej: Jefe de compras" />

                            <label class="block text-xs font-medium text-gray-700 sm:col-span-2">
                                <span>Resumen de la interacción sostenida *</span>
                                <textarea wire:model="interaction.summary" required rows="2" maxlength="3000" placeholder="¿De qué conversaron? ¿Qué necesidad o situación expuso el cliente?..." class="mt-1 block w-full rounded-xl border border-gray-200 bg-white p-3 text-xs text-gray-900 focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20"></textarea>
                            </label>

                            <x-acquisition-field label="Resultado breve *" model="interaction.result" required placeholder="Ej: Solicitó lista de precios de filtros" />
                            <x-acquisition-field label="Próxima acción convenida *" model="interaction.next_action" required placeholder="Ej: Enviar propuesta por correo y llamar" />
                            <x-acquisition-field label="Fecha de próximo seguimiento *" model="interaction.next_contact_at" type="date" required />
                        </div>

                        {{-- Botones de Acción para Acercamiento --}}
                        <div class="flex flex-wrap items-center justify-end gap-3 pt-3 border-t border-amber-200/60">
                            @if($selected->stage === 'prospectos')
                                <button
                                    type="button"
                                    wire:click="recordContactAndAdvance"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-2 rounded-xl bg-[#b50e0e] hover:bg-[#9a0101] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition-colors disabled:opacity-50"
                                >
                                    <x-workspace-icon name="check" class="h-4 w-4" />
                                    <span>Registrar contacto y avanzar a Contactados</span>
                                </button>
                                <button
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 px-4 py-2.5 text-xs font-semibold text-gray-700 transition-colors disabled:opacity-50"
                                >
                                    <span>Solo registrar contacto (sin mover etapa)</span>
                                </button>
                            @else
                                <button
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-2 rounded-xl bg-[#b50e0e] hover:bg-[#9a0101] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition-colors disabled:opacity-50"
                                >
                                    <x-workspace-icon name="check" class="h-4 w-4" />
                                    <span>Registrar nuevo contacto / seguimiento</span>
                                </button>
                            @endif
                        </div>
                    </form>
                </section>
            @endif

            {{-- SECCIÓN 4: NECESIDAD COMERCIAL Y CONTEXTO (SI NO ESTAMOS CREANDO) --}}
            @if(!$creating)
                <section class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-900 flex items-center gap-2">
                                <x-workspace-icon name="cart" class="h-4 w-4 text-[#b50e0e]" />
                                <span>Necesidad comercial y contexto</span>
                            </h2>
                            <p class="text-xs text-gray-500 mt-0.5">Requerido para avanzar a <strong>Interesados</strong> y <strong>Calificados</strong>.</p>
                        </div>
                    </div>

                    <fieldset @disabled($readOnly || !$canEdit) class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-xs font-medium text-gray-700 sm:col-span-2">
                                <span class="font-bold text-gray-800">Necesidad detectada del cliente</span>
                                <textarea wire:model="form.need" rows="2" placeholder="Ej: Requieren mantenimiento urgente de retroexcavadoras y suministro de lubricantes..." class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/60 p-3 text-xs text-gray-900 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20"></textarea>
                            </label>

                            <label class="block text-xs font-medium text-gray-700">
                                <span>Productos de interés</span>
                                <textarea wire:model="form.products" rows="2" placeholder="Ej: Filtros, aceites hidráulicos, zapatas..." class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/60 p-3 text-xs text-gray-900 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20"></textarea>
                            </label>

                            <label class="block text-xs font-medium text-gray-700">
                                <span>Servicios de interés</span>
                                <textarea wire:model="form.services" rows="2" placeholder="Ej: Overhaul de motor, diagnóstico de fallas..." class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/60 p-3 text-xs text-gray-900 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20"></textarea>
                            </label>

                            @foreach(config('acquisition.context_fields') as $key => $label)
                                <label class="block text-xs font-medium text-gray-700">
                                    <span>{{ $label }}</span>
                                    <textarea wire:model="form.context.{{ $key }}" rows="2" maxlength="3000" placeholder="{{ $label }}..." class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/60 p-3 text-xs text-gray-900 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20"></textarea>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </section>

                {{-- SECCIÓN 5: MAQUINARIA Y ACTIVOS CONOCIDOS --}}
                <section class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-900 flex items-center gap-2">
                                <x-workspace-icon name="truck" class="h-4 w-4 text-[#b50e0e]" />
                                <span>Maquinaria / Flota del cliente</span>
                            </h2>
                            <p class="text-xs text-gray-500 mt-0.5">Equipos donde se aplicarán los repuestos o servicios.</p>
                        </div>

                        @if(!$readOnly && $canEdit)
                            <button
                                type="button"
                                wire:click="addMachine"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-colors shadow-2xs"
                            >
                                <x-workspace-icon name="plus" class="h-3.5 w-3.5 text-[#b50e0e]" />
                                <span>Agregar equipo</span>
                            </button>
                        @endif
                    </div>

                    <div class="space-y-4">
                        @forelse($form['machines'] ?? [] as $index => $machine)
                            <div wire:key="machine-{{ $index }}" class="rounded-xl border border-gray-200/90 bg-gray-50/40 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Equipo #{{ $index + 1 }}</span>
                                    @if(!$readOnly && $canEdit)
                                        <button
                                            type="button"
                                            wire:click="removeMachine({{ $index }})"
                                            class="text-xs font-semibold text-rose-700 hover:text-rose-900 transition-colors"
                                        >
                                            Quitar equipo
                                        </button>
                                    @endif
                                </div>

                                <div class="grid gap-3 sm:grid-cols-3">
                                    <x-acquisition-field label="Tipo de equipo *" model="form.machines.{{ $index }}.type" placeholder="Ej: Excavadora, Generador" />
                                    <x-acquisition-field label="Marca" model="form.machines.{{ $index }}.brand" placeholder="Ej: Caterpillar, Komatsu" />
                                    <x-acquisition-field label="Modelo" model="form.machines.{{ $index }}.model" placeholder="Ej: 320D" />
                                    <x-acquisition-field label="Serie / Chasis" model="form.machines.{{ $index }}.serial" placeholder="Número de serie" />
                                    <x-acquisition-field label="Motor" model="form.machines.{{ $index }}.engine" placeholder="Ej: CAT C6.4, Cummins 6BT" />
                                    <x-acquisition-field label="Cantidad *" model="form.machines.{{ $index }}.quantity" type="number" min="1" />

                                    <label class="block text-xs font-medium text-gray-700 sm:col-span-3">
                                        <span>Observaciones técnicas</span>
                                        <textarea wire:model="form.machines.{{ $index }}.notes" rows="1" placeholder="Condición del equipo, horas de uso..." class="mt-1 block w-full rounded-xl border border-gray-200 bg-white p-2.5 text-xs text-gray-900 focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20"></textarea>
                                    </label>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-500">Sin maquinaria registrada. Si conoces la flota del prospecto, agrégala para facilitar la cotización de repuestos.</p>
                        @endforelse
                    </div>
                </section>

                {{-- SECCIÓN 6: CLASIFICACIÓN Y CRITERIOS COMERCIALES --}}
                <section class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-900 flex items-center gap-2">
                                <x-workspace-icon name="settings" class="h-4 w-4 text-[#b50e0e]" />
                                <span>Clasificación y Criterios Comerciales</span>
                            </h2>
                            <p class="text-xs text-gray-500 mt-0.5">Evalúa el potencial de compra para la etapa de <strong>Calificados</strong>.</p>
                        </div>
                    </div>

                    <fieldset @disabled(!auth()->user()->hasPermissionTo('prospects.classify') || $readOnly) class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach(config('acquisition.criteria') as $key => $label)
                                <label class="block text-xs font-medium text-gray-700">
                                    <span>{{ $label }}</span>
                                    <select wire:model="form.classification_criteria.{{ $key }}" class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/60 py-2 px-3 text-xs text-gray-900 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20">
                                        <option value="">Sin evaluar</option>
                                        @foreach(config('acquisition.levels') as $level)
                                            <option value="{{ $level }}">{{ $level }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endforeach
                        </div>

                        <div class="pt-2 border-t border-gray-100">
                            <label class="block text-xs font-bold text-gray-900">
                                <span>Clasificación manual del prospecto</span>
                                <select wire:model="form.classification" class="mt-1 block w-full sm:max-w-xs rounded-xl border border-gray-200 bg-white py-2 px-3 text-xs font-bold text-gray-900 focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20">
                                    <option value="">Sin clasificar</option>
                                    @foreach(config('acquisition.classes') as $key => $label)
                                        <option value="{{ $key }}">{{ $key }} — {{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <p class="text-[11px] text-gray-400 mt-1">La clasificación (A, B o C) es requerida para mover a Calificados.</p>
                        </div>
                    </fieldset>
                </section>
            @endif
        </div>

        {{-- COLUMNA LATERAL (1/3 DEL ANCHO): GESTIÓN DE ETAPA, GUARDADO, DUPLICADOS E HISTORIAL --}}
        <div class="lg:col-span-1 space-y-6">
            {{-- PANEL A: CONTROL DE ETAPAS DEL PIPELINE KANBAN --}}
            @if($selected && !$readOnly)
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-2xs space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-900 flex items-center gap-1.5 border-b border-gray-100 pb-2.5">
                        <x-workspace-icon name="target" class="h-3.5 w-3.5 text-[#b50e0e]" />
                        <span>Etapa en el Tablero Kanban</span>
                    </h3>

                    {{-- Stepper visual de etapas --}}
                    <div class="space-y-2">
                        @foreach(config('acquisition.stages') as $stageKey => $stageLabel)
                            @php
                                $isCurrent = $selected->stage === $stageKey;
                                $stagesList = array_keys(config('acquisition.stages'));
                                $currentIndex = array_search($selected->stage, $stagesList);
                                $thisIndex = array_search($stageKey, $stagesList);
                                $isPast = $thisIndex < $currentIndex;
                            @endphp
                            <div class="flex items-center gap-2.5 p-2 rounded-xl transition-colors {{ $isCurrent ? 'bg-rose-50 border border-rose-200 text-[#b50e0e]' : ($isPast ? 'bg-gray-50 text-gray-700' : 'text-gray-400') }}">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $isCurrent ? 'bg-[#b50e0e] text-white' : ($isPast ? 'bg-slate-700 text-white' : 'bg-gray-200 text-gray-500') }}">
                                    @if($isPast)
                                        ✓
                                    @else
                                        {{ $thisIndex + 1 }}
                                    @endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold {{ $isCurrent ? 'text-[#b50e0e]' : ($isPast ? 'text-gray-900' : 'text-gray-500') }}">
                                        {{ $stageLabel }}
                                    </p>
                                </div>
                                @if($isCurrent)
                                    <span class="text-[10px] font-bold uppercase tracking-wider bg-white px-2 py-0.5 rounded-md border border-rose-200 text-[#b50e0e]">
                                        Actual
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Formulario para mover de etapa --}}
                    @if($canMove)
                        <form wire:submit="moveSelected" class="pt-3 border-t border-gray-100 space-y-2.5">
                            <label class="block text-xs font-medium text-gray-700">
                                <span>Cambiar etapa directamente:</span>
                                <select wire:model="targetStage" class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/60 py-2 px-3 text-xs font-semibold text-gray-800 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20">
                                    @foreach(config('acquisition.stages') as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 py-2 px-3 text-xs font-semibold text-gray-700 transition-colors shadow-2xs disabled:opacity-50"
                            >
                                <x-workspace-icon name="arrow" class="h-3 w-3 text-gray-500" />
                                <span>Actualizar etapa</span>
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- PANEL B: ACCIÓN DE GUARDAR Y DUPLICADOS --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-2xs space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-900 border-b border-gray-100 pb-2.5">
                    Acciones del prospecto
                </h3>

                @if(!$readOnly && $canEdit)
                    <button
                        type="button"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#b50e0e] hover:bg-[#9a0101] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors disabled:opacity-50"
                    >
                        <x-workspace-icon name="check" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="save">{{ $creating ? 'Crear prospecto' : 'Guardar cambios' }}</span>
                        <span wire:loading wire:target="save">Guardando...</span>
                    </button>
                @endif

                <button
                    type="button"
                    wire:click="close"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-700 transition-colors shadow-2xs"
                >
                    <x-workspace-icon name="arrow" class="h-3.5 w-3.5 rotate-180 text-gray-500" />
                    <span>Volver al tablero Kanban</span>
                </button>

                {{-- Posibles Duplicados --}}
                @if(!$readOnly && $canEdit)
                    <div class="pt-3 border-t border-gray-100 space-y-2">
                        <button type="button" wire:click="$refresh" class="text-xs font-semibold text-slate-700 hover:text-slate-900 underline">
                            Revisar posibles duplicados
                        </button>

                        @if($matches)
                            <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-3 space-y-1.5 text-xs text-amber-900">
                                <p class="font-bold">Coincidencias detectadas:</p>
                                <ul class="space-y-1">
                                    @foreach($matches as $match)
                                        <li class="flex items-center justify-between gap-2">
                                            <span>&bull; {{ $match['name'] }} ({{ $match['exact'] ? 'Identificación exacta' : 'Nombre coincidente' }})</span>
                                            @if($match['type'] === 'prospect' && $match['id'])
                                                <button type="button" wire:click="open({{ $match['id'] }})" class="underline font-bold text-amber-950">
                                                    Abrir
                                                </button>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <label class="flex items-start gap-2 pt-1 text-xs text-gray-600 cursor-pointer">
                            <input type="checkbox" wire:model="duplicateAck" class="rounded border-gray-300 text-[#b50e0e] focus:ring-[#b50e0e]/20 mt-0.5" />
                            <span>Confirmé que es otro registro si hay coincidencia de nombre.</span>
                        </label>

                        @if(auth()->user()->hasPermissionTo('crm.clients.manage'))
                            <div class="pt-2">
                                <label class="block text-xs font-medium text-gray-700">
                                    <span>Vincular cliente existente (opcional)</span>
                                    <input
                                        type="search"
                                        wire:model.live.debounce.400ms="clientSearch"
                                        placeholder="Buscar por nombre o RUC..."
                                        class="mt-1 block w-full rounded-xl border border-gray-200 bg-gray-50/60 py-1.5 px-3 text-xs text-gray-900 focus:bg-white focus:border-[#b50e0e] focus:ring-2 focus:ring-[#b50e0e]/20"
                                    />
                                </label>

                                <select wire:model="form.entity_id" aria-label="Cliente existente" class="mt-2 block w-full rounded-xl border border-gray-200 bg-white py-1.5 px-3 text-xs text-gray-800">
                                    <option value="">Sin cliente vinculado</option>
                                    @if($selected?->entity)
                                        <option value="{{ $selected->entity_id }}">{{ $selected->entity->name }}</option>
                                    @endif
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}">{{ $client->name }} {{ $client->rfc_ruc }}</option>
                                    @endforeach
                                    @foreach($matches as $match)
                                        @if($match['type'] === 'client' && $match['id'])
                                            <option value="{{ $match['id'] }}">Cliente coincidente #{{ $match['id'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- PANEL C: HISTORIAL DE CONTACTOS Y ACTIVIDADES --}}
            @if($selected)
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-2xs space-y-3">
                    <div class="border-b border-gray-100 pb-2.5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-900 flex items-center gap-1.5">
                            <x-workspace-icon name="history" class="h-3.5 w-3.5 text-gray-500" />
                            <span>Historial y Bitácora</span>
                        </h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">
                            Último contacto: <strong class="text-gray-700">{{ $selected->last_contact_at?->format('d/m/Y H:i') ?? 'Sin registrar' }}</strong> {{ $selected->last_contact_channel }}
                        </p>
                        @if($selected->last_result)
                            <p class="text-[11px] text-gray-600 mt-0.5">
                                Resultado: <span class="font-medium text-gray-800">{{ $selected->last_result }}</span>
                            </p>
                        @endif
                    </div>

                    <ol class="divide-y divide-gray-100 space-y-2.5 pt-1">
                        @forelse($history as $event)
                            <li class="pt-2 text-xs text-gray-700 first:pt-0">
                                <div class="flex items-center justify-between text-[11px] text-gray-400">
                                    <span>
                                        {{ $event->action === 'contact' && !empty($event->metadata['date']) ? \Carbon\Carbon::parse($event->metadata['date'])->format('d/m/Y H:i') : $event->created_at->format('d/m/Y H:i') }}
                                    </span>
                                    <span class="font-medium text-gray-600">{{ $event->user?->name ?? 'Sistema' }}</span>
                                </div>
                                <p class="mt-1 font-semibold text-gray-900">{{ $event->description }}</p>
                                @if($event->action === 'contact')
                                    @if(!empty($event->metadata['position']))
                                        <p class="mt-0.5 text-xs text-gray-500">Cargo: {{ $event->metadata['position'] }}</p>
                                    @endif
                                    @if(!empty($event->metadata['summary']))
                                        <p class="mt-0.5 text-xs text-gray-600">Resumen: {{ $event->metadata['summary'] }}</p>
                                    @endif
                                    <p class="mt-0.5 text-xs text-gray-600">Resultado: {{ $event->metadata['result'] ?? '' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">Próxima acción: {{ $event->metadata['next_action'] ?? '' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">Próximo seguimiento: {{ $event->metadata['next_contact_at'] ?? '' }}</p>
                                @endif
                            </li>
                        @empty
                            <li class="py-2 text-xs text-gray-400">Sin eventos de historial registrados.</li>
                        @endforelse
                    </ol>

                    @if($historyCount > $history->count())
                        <button
                            type="button"
                            wire:click="moreHistory"
                            wire:loading.attr="disabled"
                            class="w-full text-center text-xs font-semibold text-slate-700 hover:text-slate-900 underline pt-2"
                        >
                            Ver contactos anteriores ({{ $historyCount - $history->count() }} restantes)
                        </button>
                    @endif
                </div>

                {{-- PANEL D: DESCARTE DE PROSPECTO --}}
                @if(!$readOnly && auth()->user()->hasPermissionTo('prospects.discard'))
                    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-2xs">
                        <details class="text-xs text-gray-600">
                            <summary class="cursor-pointer font-bold text-rose-700 hover:text-rose-900 transition-colors">
                                Descartar este prospecto
                            </summary>
                            <form wire:submit="discard" class="mt-3 space-y-3 pt-2 border-t border-gray-100">
                                <label class="block text-xs font-medium text-gray-700">
                                    <span>Motivo de descarte *</span>
                                    <select wire:model="discardReason" required class="mt-1 block w-full rounded-xl border border-gray-200 bg-white py-1.5 px-3 text-xs text-gray-900">
                                        <option value="">Seleccionar motivo</option>
                                        @foreach(config('acquisition.discard_reasons') as $reason)
                                            <option value="{{ $reason }}">{{ $reason }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <x-acquisition-field label="Detalle (requerido para 'Otro')" model="discardNotes" placeholder="Explicación del descarte..." />
                                <button
                                    type="submit"
                                    wire:confirm="¿Descartar este prospecto conservando su historial comercial?"
                                    wire:loading.attr="disabled"
                                    class="w-full rounded-xl border border-rose-300 bg-rose-50 hover:bg-rose-100 py-2 px-3 text-xs font-bold text-rose-800 transition-colors"
                                >
                                    Confirmar descarte
                                </button>
                            </form>
                        </details>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
