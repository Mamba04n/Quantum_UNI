<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-lg font-black text-slate-950">Condiciones de envio</h2>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <label class="block">
            <span class="text-xs font-black uppercase text-slate-500">Metodo de envio</span>
            <select wire:model="shipping_method" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                <option value="">Seleccionar</option>
                <option>Envio aereo</option>
                <option>Envio maritimo</option>
                <option>Courier</option>
                <option>Transporte terrestre</option>
            </select>
        </label>
        <label class="block">
            <span class="text-xs font-black uppercase text-slate-500">Incoterm</span>
            <select wire:model="incoterm" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                <option value="">Seleccionar</option>
                <option>FOB</option>
                <option>CIF</option>
                <option>EXW</option>
                <option>DAP</option>
            </select>
        </label>
        <label class="block">
            <span class="text-xs font-black uppercase text-slate-500">Condiciones</span>
            <input wire:model="shipping_terms" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-black uppercase text-slate-500">Transportista</span>
            <input wire:model="carrier_name" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-black uppercase text-slate-500">Fecha estimada envio</span>
            <input wire:model="estimated_shipping_date" type="date" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-black uppercase text-slate-500">Fecha estimada llegada</span>
            <input wire:model="estimated_arrival_date" type="date" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
        </label>
        <label class="block md:col-span-2">
            <span class="text-xs font-black uppercase text-slate-500">Comentarios de envio</span>
            <textarea wire:model="shipping_comments" rows="3" class="mt-1 w-full rounded-xl border-slate-200 text-sm"></textarea>
        </label>
    </div>
</section>
