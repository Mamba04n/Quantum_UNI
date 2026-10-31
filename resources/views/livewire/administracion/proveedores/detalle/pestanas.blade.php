<div class="border-b border-slate-200 px-5 py-3">
    <label class="block sm:hidden"><span class="sr-only">Sección del proveedor</span><select wire:model.live="activeTab" class="h-11 w-full rounded-lg border-slate-300 bg-white text-sm"><option value="general">Información general</option><option value="brands">Marcas</option><option value="products">Productos</option><option value="machines">Maquinaria compatible</option><option value="payments">Métodos de pago</option><option value="banks">Datos bancarios</option><option value="history">Historial</option></select></label>
    <div class="hidden grid-cols-7 gap-1 rounded-lg bg-slate-100 p-1 sm:grid">
        @foreach(['general' => 'Informacion general', 'brands' => 'Marcas', 'products' => 'Productos', 'machines' => 'Maquinaria compatible', 'payments' => 'Metodos de pago', 'banks' => 'Datos bancarios', 'history' => 'Historial'] as $tab => $label)
            <button wire:click="$set('activeTab', '{{ $tab }}')" class="rounded-md px-2 py-2.5 text-center text-xs font-semibold {{ $activeTab === $tab ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">{{ $label }}</button>
        @endforeach
    </div>
</div>
