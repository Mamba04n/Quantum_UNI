<div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">Solicitud de Cliente</h2>
            <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">Sin guardar</span>
        </div>
        <p class="mt-0.5 text-sm text-gray-500">Captura solicitudes externas recibidas.</p>
    </div>
    <div class="flex shrink-0 items-center gap-3 sm:flex-row sm:items-center">
        <div class="text-sm font-semibold text-slate-700"><span class="sr-only">No. referencia </span>{{ $reference }}</div>
        <label for="request-date" class="sr-only">Fecha de solicitud</label>
        <input id="request-date" type="date" wire:model="date" class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold text-gray-800">
        @error('date') <span role="alert" class="text-xs text-red-500">{{ $message }}</span> @enderror
    </div>
</div>
