<div class="p-6 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white rounded-t-2xl">
    <div>
        <h3 class="text-base font-extrabold text-gray-900">{{ $editingId ? 'Editar empleado' : 'Nuevo empleado' }}</h3>
        <p class="text-xs text-gray-400 mt-0.5">Ficha interna, cuenta de acceso, roles y estado.</p>
    </div>
    <button wire:click="$set('showModal', false)" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>
