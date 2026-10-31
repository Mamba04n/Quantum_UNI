<div class="px-6 py-4 flex gap-3 sticky bottom-0 bg-white border-t border-gray-100 rounded-b-2xl">
    <button wire:click="$set('showModal', false)" class="flex-1 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-50 transition-colors">Cancelar</button>
    <button wire:click="save" class="flex-[2] py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-xl transition-colors">
        {{ $editingId ? 'Guardar cambios' : 'Guardar empleado' }}
    </button>
</div>
