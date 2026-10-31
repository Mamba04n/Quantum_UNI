<section>
    <h3 class="mb-3 text-sm font-semibold text-gray-900">Cliente y origen</h3>
    <div class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
        <div class="min-w-0 sm:col-span-2">
            <label for="request-client" class="block mb-1.5 text-sm font-bold text-gray-700">Vincular cliente existente</label>
            <div class="grid grid-cols-1 items-start gap-2 sm:grid-cols-2">
            <div class="relative">
            <div class="flex gap-2">
                <input aria-label="Buscar cliente existente" type="search" wire:model.live.debounce.300ms="clientSearch" class="min-w-0 w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Escribir para buscar cliente...">
                @if($clientSearch !== '')
                    <button type="button" wire:click="clearClientSearch" class="rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-600 hover:bg-slate-50">Limpiar</button>
                @endif
            </div>
            @if($clientSearch !== '' && blank($selectedClientId))
                <div class="absolute left-0 right-0 top-full z-20 mt-1 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white text-xs shadow-sm">
                    @forelse($clients as $client)
                        <button type="button" wire:click="selectClientOption({{ $client->id }})" class="block w-full px-4 py-2.5 text-left hover:bg-slate-50 border-b border-slate-100 last:border-0">
                            <span class="block font-black text-slate-900">{{ $client->name }}</span>
                            <span class="block text-[10px] text-slate-500">{{ $client->contact_name ?: 'Sin contacto' }}{{ $client->rfc_ruc ? ' - RUC: '.$client->rfc_ruc : '' }}</span>
                        </button>
                    @empty
                        <div class="px-4 py-3 font-bold text-slate-400 text-xs">No hay clientes con esa búsqueda.</div>
                    @endforelse
                </div>
            @endif
            </div>
            <select id="request-client" wire:model.live="selectedClientId" class="w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm font-bold">
                <option value="">Manual / Sin vincular</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
            </select>
            </div>
            <span class="mt-1 block text-[10px] font-bold text-slate-400">{{ $clients->count() }} cliente(s) disponibles</span>
        </div>
        <div class="min-w-0 ">
            <label for="request-sourceChannel" class="block mb-1.5 text-sm font-bold text-gray-700">Origen</label>
            <select id="request-sourceChannel" wire:model="sourceChannel" class="w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm">
                @foreach($sourceLabels as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('sourceChannel') <span role="alert" class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div class="min-w-0 ">
            <label for="request-customerContactName" class="block mb-1.5 text-sm font-bold text-gray-700">Persona de contacto</label>
            <input id="request-customerContactName" wire:model="customerContactName" type="text" class="w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm" placeholder="Nombre completo">
            @error('customerContactName') <span role="alert" class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div class="min-w-0 sm:col-span-2">
            <label for="request-quoteToName" class="block mb-1.5 text-sm font-bold text-gray-700">Nombre del cliente</label>
            <input id="request-quoteToName" wire:model="quoteToName" type="text" class="w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm">
            @error('quoteToName') <span role="alert" class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div class="min-w-0 ">
            <label for="request-quoteToEmail" class="block mb-1.5 text-sm font-bold text-gray-700">Correo</label>
            <input id="request-quoteToEmail" wire:model="quoteToEmail" type="email" class="w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm" placeholder="opcional">
            @error('quoteToEmail') <span role="alert" class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div class="min-w-0 ">
            <label for="request-quoteToRuc" class="block mb-1.5 text-sm font-bold text-gray-700">RUC</label>
            <input id="request-quoteToRuc" wire:model="quoteToRuc" type="text" class="w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm" placeholder="opcional">
            @error('quoteToRuc') <span role="alert" class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
        </div>
    </div>
</section>
