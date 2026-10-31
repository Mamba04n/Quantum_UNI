<div class="grid gap-5 lg:grid-cols-3">
    <section class="lg:col-span-2">
        <h2 class="text-base font-semibold text-slate-900">Información empresarial</h2>
        <dl class="mt-3 grid gap-x-6 border-y border-slate-200 sm:grid-cols-2">
            @foreach([
                'Razón social' => $selectedSupplier->legal_name,
                'RUC / identificación fiscal' => $selectedSupplier->rfc_ruc,
                'País' => $selectedSupplier->country,
                'Ciudad' => $selectedSupplier->city,
                'Correo institucional' => $selectedSupplier->email,
                'Teléfono' => $selectedSupplier->phone_mobile,
                'Sitio web' => $selectedSupplier->website,
                'Estado operativo' => str($selectedSupplier->status ?: 'activo')->headline(),
            ] as $label => $value)
                <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ filled($value) ? $value : 'No registrado' }}</dd></div>
            @endforeach
        </dl>
        <div class="mt-4"><p class="text-xs text-slate-500">Dirección</p><p class="mt-1 text-sm text-slate-800">{{ $selectedSupplier->address ?: $selectedSupplier->exact_address ?: 'No registrada' }}</p></div>
    </section>
    <aside class="space-y-5">
        <section class="rounded-lg border border-slate-200 p-4"><h2 class="text-sm font-semibold text-slate-900">Capacidad verificada</h2><dl class="mt-3 space-y-3"><div><dt class="text-xs text-slate-500">Calidades</dt><dd class="mt-1 text-sm text-slate-800">{{ collect($selectedSupplier->quality_types ?? [])->implode(', ') ?: 'No clasificadas' }}</dd></div><div><dt class="text-xs text-slate-500">Representación</dt><dd class="mt-1 text-sm text-slate-800">{{ data_get($selectedSupplier->supplier_metadata, 'representation') ? 'Registrada' : 'No especificada' }}</dd></div><div><dt class="text-xs text-slate-500">Garantías</dt><dd class="mt-1 text-sm text-slate-800">{{ collect(data_get($selectedSupplier->supplier_metadata, 'warranties', []))->implode(', ') ?: 'No registradas' }}</dd></div></dl></section>
    </aside>
</div>

<section class="mt-6 border-t border-slate-200 pt-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Personas de contacto</h2>
            <p class="mt-0.5 text-sm text-slate-500">Responsables comerciales, administrativos y logísticos del proveedor.</p>
        </div>
        @canany(['supplier.edit', 'supplier_bid.edit_supplier_response'])
            <button type="button" wire:click="$toggle('showContactForm')" class="inline-flex items-center justify-center rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-slate-900">
                Agregar contacto
            </button>
        @endcanany
    </div>

    @if($showContactForm)
        <form wire:submit="addContact" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <label class="text-sm text-slate-700">Nombre completo<input wire:model="newContactName" type="text" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('newContactName')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm text-slate-700">Cargo<input wire:model="newContactPosition" type="text" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm text-slate-700">Área o departamento<input wire:model="newContactDepartment" type="text" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm text-slate-700">Correo<input wire:model="newContactEmail" type="email" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('newContactEmail')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm text-slate-700">Teléfono móvil<input wire:model="newContactMobile" type="text" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm text-slate-700">Teléfono de oficina<input wire:model="newContactOfficePhone" type="text" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm text-slate-700">WhatsApp<input wire:model="newContactWhatsapp" type="text" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm text-slate-700 lg:col-span-2">Notas<input wire:model="newContactNotes" type="text" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
            </div>
            <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-700"><input wire:model="newContactIsPrimary" type="checkbox" class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">Usar como contacto principal</label>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="$set('showContactForm', false)" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Guardar contacto</button>
            </div>
        </form>
    @endif

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
        @forelse($selectedSupplier->contacts as $contact)
            <article class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 last:border-b-0 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-slate-900">{{ $contact->name }}</h3>
                        @if($contact->is_primary)<span class="rounded border border-teal-200 bg-teal-50 px-2 py-0.5 text-[11px] font-semibold text-teal-700">Principal</span>@endif
                    </div>
                    <p class="mt-0.5 text-sm text-slate-500">{{ collect([$contact->position, $contact->department])->filter()->implode(' · ') ?: 'Cargo no registrado' }}</p>
                    <p class="mt-1 break-words text-xs text-slate-500">{{ collect([$contact->email, $contact->mobile ?: $contact->office_phone, $contact->whatsapp ? 'WhatsApp '.$contact->whatsapp : null])->filter()->implode(' · ') ?: 'Sin medios de contacto registrados' }}</p>
                </div>
                @canany(['supplier.edit', 'supplier_bid.edit_supplier_response'])
                    <div class="flex shrink-0 items-center gap-3">
                        @unless($contact->is_primary)<button type="button" wire:click="setPrimaryContact({{ $contact->id }})" class="text-xs font-semibold text-slate-600 hover:text-slate-950">Definir como principal</button>@endunless
                        <button type="button" wire:click="deleteContact({{ $contact->id }})" wire:confirm="¿Deseas eliminar este contacto?" class="text-xs font-medium text-rose-600 hover:text-rose-700">Eliminar</button>
                    </div>
                @endcanany
            </article>
        @empty
            <p class="px-4 py-8 text-center text-sm text-slate-500">No hay personas de contacto registradas.</p>
        @endforelse
    </div>
</section>
