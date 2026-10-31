<section aria-labelledby="request-evidence-heading" class="min-w-0">
    <h3 id="request-evidence-heading" class="mb-1.5 text-sm font-semibold text-gray-900">Evidencia</h3>
    <label for="request-evidence" class="sr-only">Imagen o archivo de prueba</label>
    <input id="request-evidence" wire:model="evidenceAttachment" type="file" class="block w-full min-w-0 text-sm sm:max-w-lg file:mr-3 file:rounded-xl file:border file:border-gray-200 file:bg-white file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-50">
    <div wire:loading wire:target="evidenceAttachment" role="status" class="mt-2 text-sm text-slate-600">Subiendo evidencia...</div>
    @error('evidenceAttachment') <span role="alert" class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
    @if($evidenceAttachment)
        <div class="mt-3 flex min-w-0 flex-wrap items-start gap-3">
            @if(str_starts_with($evidenceAttachment->getMimeType(), 'image/'))
                <img src="{{ $evidenceAttachment->temporaryUrl() }}" alt="Vista previa de la evidencia" class="max-h-32 max-w-full rounded-lg border border-gray-100 object-contain">
            @endif
            <p class="min-w-0 break-all text-sm text-gray-600">{{ $evidenceAttachment->getClientOriginalName() }}</p>
        </div>
    @endif
</section>
