        <div class="min-w-0">
            <label for="request-description" class="block mb-1.5 text-sm font-bold text-gray-700">Notas / mensaje recibido</label>
            <textarea style="field-sizing: content" id="request-description" wire:model="description" rows="2" class="min-h-20 w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm" placeholder="Copia aqui el mensaje del cliente o resume la solicitud."></textarea>
            @error('description') <span role="alert" class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
        </div>
