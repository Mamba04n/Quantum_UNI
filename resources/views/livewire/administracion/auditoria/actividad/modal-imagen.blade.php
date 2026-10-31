<div x-show="imageModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-6" @click.self="imageModal = null" style="display: none;">
    <div class="max-w-4xl w-full bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex justify-between items-center px-4 py-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">Imagen del movimiento</h3>
            <button type="button" @click="imageModal = null" class="text-gray-500 hover:text-gray-700 text-sm font-bold">Cerrar</button>
        </div>
        <div class="p-4 bg-gray-50">
            <img :src="imageModal" alt="Imagen del movimiento" class="w-full max-h-[70vh] object-contain rounded-xl bg-white">
        </div>
    </div>
</div>
