<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-lg font-black text-slate-950">Consignatario</h2>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        @foreach([
            ['consignee_name', 'Nombre', 'Quantum Soluciones S.A.'],
            ['consignee_country', 'Pais', 'Nicaragua'],
            ['consignee_city', 'Ciudad', 'Managua'],
            ['consignee_phone', 'Numero celular', '+505 8326 2783'],
            ['consignee_address_1', 'Direccion 1', 'Direccion principal'],
            ['consignee_address_2', 'Direccion 2', 'Referencia adicional'],
            ['consignee_emails', 'Correos', 'correo1@empresa.com, correo2@empresa.com'],
        ] as [$field, $label, $placeholder])
            <label class="block {{ in_array($field, ['consignee_address_1', 'consignee_address_2', 'consignee_emails'], true) ? 'md:col-span-2' : '' }}">
                <span class="text-xs font-black uppercase text-slate-500">{{ $label }}</span>
                <input wire:model="{{ $field }}" class="mt-1 w-full rounded-xl border-slate-200 text-sm" placeholder="{{ $placeholder }}">
                @error($field) <span class="text-xs font-bold text-rose-700">{{ $message }}</span> @enderror
            </label>
        @endforeach
        <label class="block md:col-span-2">
            <span class="text-xs font-black uppercase text-slate-500">Comentario consignatario</span>
            <textarea wire:model="consignee_comment" rows="3" class="mt-1 w-full rounded-xl border-slate-200 text-sm"></textarea>
        </label>
        <label class="block md:col-span-2">
            <span class="text-xs font-black uppercase text-red-700">Comentario urgente</span>
            <textarea wire:model="consignee_urgent_comment" rows="3" class="mt-1 w-full rounded-xl border-red-200 text-sm" placeholder="Se destacara en el PDF si existe"></textarea>
        </label>
    </div>
</section>
