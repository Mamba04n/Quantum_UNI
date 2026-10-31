<section class="mx-auto max-w-6xl">
    <button type="button" wire:click="backToList" class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-900"><span aria-hidden="true">‹</span> Volver a proveedores</button>
    <form wire:submit="save" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-200 px-6 py-5"><h1 class="text-2xl font-semibold text-slate-950">{{ $selectedSupplierId ? 'Modificar proveedor' : 'Registrar proveedor' }}</h1><p class="mt-1 text-sm text-slate-500">Completa la identidad fiscal, contacto, ubicación y capacidades comerciales.</p></header>
        <div class="divide-y divide-slate-200">
            <fieldset class="grid gap-4 p-6 md:grid-cols-2">
                <legend class="mb-4 text-base font-semibold text-slate-900">Identificación empresarial</legend>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">Nombre comercial</span><input wire:model="name" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm">@error('name')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">Razón social</span><input wire:model="legalName" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm"></label>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">RUC / identificación fiscal</span><input wire:model="taxId" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm"></label>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">Estado operativo</span><select wire:model="status" class="h-11 w-full rounded-lg border-slate-300 text-sm"><option value="activo">Activo</option><option value="inactivo">Inactivo</option></select></label>
            </fieldset>
            <fieldset class="grid gap-4 p-6 md:grid-cols-2 lg:grid-cols-3">
                <legend class="mb-4 text-base font-semibold text-slate-900">Contacto y ubicación</legend>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">Correo institucional</span><input wire:model="email" type="email" class="h-11 w-full rounded-lg border-slate-300 text-sm">@error('email')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">Teléfono</span><input wire:model="phone" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm"></label>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">WhatsApp</span><input wire:model="whatsapp" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm"></label>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">Persona de contacto</span><input wire:model="contactName" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm"></label>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">Cargo</span><input wire:model="contactPosition" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm"></label>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">Sitio web</span><input wire:model="website" type="url" class="h-11 w-full rounded-lg border-slate-300 text-sm" placeholder="https://">@error('website')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">País</span><input wire:model="country" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm"></label>
                <label><span class="mb-1.5 block text-sm font-medium text-slate-700">Ciudad</span><input wire:model="city" type="text" class="h-11 w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="md:col-span-2 lg:col-span-3"><span class="mb-1.5 block text-sm font-medium text-slate-700">Dirección</span><textarea wire:model="address" rows="2" class="w-full rounded-lg border-slate-300 text-sm"></textarea></label>
            </fieldset>
            <fieldset class="p-6">
                <legend class="text-base font-semibold text-slate-900">Calidades que puede suministrar</legend><p class="mt-1 text-sm text-slate-500">Selecciona únicamente las clasificaciones verificadas para este proveedor.</p>
                <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($qualityTypeOptions as $code => $label)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 px-4 py-3 hover:bg-slate-50"><input wire:model="qualityTypes" type="checkbox" value="{{ $code }}" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"><span><span class="block text-sm font-semibold text-slate-900">{{ $code }}</span><span class="block text-xs text-slate-500">{{ $label }}</span></span></label>
                    @endforeach
                </div>
                @error('qualityTypes')<p class="mt-2 text-xs text-rose-700">{{ $message }}</p>@enderror
            </fieldset>
        </div>
        <footer class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4"><button type="button" wire:click="backToList" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Cancelar</button><button type="submit" class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Guardar proveedor</button></footer>
    </form>
</section>
