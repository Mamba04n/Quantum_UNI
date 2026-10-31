<section class="border-t border-gray-100 pt-4 lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0">
    <h3 class="mb-3 text-sm font-semibold text-gray-900">Equipo y solicitud</h3>
    <div class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
        <div class="min-w-0 sm:col-span-2">
            <label for="request-clientMachineId" class="block mb-1.5 text-sm font-bold text-gray-700">Maquinaria registrada del cliente</label>
            <select id="request-clientMachineId" wire:model.live="clientMachineId" class="w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm">
                <option value="">No vincular maquinaria</option>
                @foreach($clientMachines as $machine)
                    <option value="{{ $machine->id }}">{{ $machine->name }} &middot; Serie {{ $machine->serial_number ?: 'N/A' }}</option>
                @endforeach
            </select>
            @error('clientMachineId') <span role="alert" class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
            @if($selectedClientId && $clientMachines->isEmpty())
                <span class="mt-1 block text-xs text-gray-500">Este cliente no tiene maquinaria registrada.</span>
            @endif
        </div>
        <div class="min-w-0 sm:col-span-2">
            <label for="request-machinery" class="block mb-1.5 text-sm font-bold text-gray-700">Maquinaria</label>
            <input id="request-machinery" wire:model="machinery" type="text" class="w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm">
            @error('machinery') <span role="alert" class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div class="min-w-0 ">
            <label for="request-machineBrand" class="block mb-1.5 text-sm font-bold text-gray-700">Marca</label>
            <input id="request-machineBrand" wire:model="machineBrand" type="text" class="w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm">
            @error('machineBrand') <span role="alert" class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
        </div>
        <div class="min-w-0 ">
            <label for="request-machineModel" class="block mb-1.5 text-sm font-bold text-gray-700">Modelo del equipo</label>
            <input id="request-machineModel" wire:model="machineModel" type="text" class="w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm">
            @error('machineModel') <span role="alert" class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
        </div>
        <div class="min-w-0 ">
            <label for="request-equipmentSerial" class="block mb-1.5 text-sm font-bold text-gray-700">Serie del equipo</label>
            <input id="request-equipmentSerial" wire:model="equipmentSerial" type="text" class="w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm">
            @error('equipmentSerial') <span role="alert" class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
        </div>
        <div class="min-w-0 ">
            <label for="request-requestUrgency" class="block mb-1.5 text-sm font-bold text-gray-700">Urgencia</label>
            <select id="request-requestUrgency" wire:model="requestUrgency" class="w-full bg-white border-gray-200 rounded-xl py-2.5 px-3 text-sm">
                @foreach($urgencyLabels as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('requestUrgency') <span role="alert" class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
        </div>
    </div>
</section>
