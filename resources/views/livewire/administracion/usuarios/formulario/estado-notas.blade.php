<section class="rounded-2xl border border-gray-100 p-5">
    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl mb-4">
        <span class="text-sm font-semibold text-gray-700">Usuario activo</span>
        <button type="button" wire:click="$set('is_active', !{{ $is_active ? 'true' : 'false' }})" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $is_active ? 'bg-teal-500' : 'bg-gray-300' }}">
            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform {{ $is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
        </button>
    </div>
    <textarea wire:model="notes" rows="3" placeholder="Notas internas administrativas" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all"></textarea>
</section>
