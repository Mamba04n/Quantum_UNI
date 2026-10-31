<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
        <input wire:model.live.debounce.500ms="search" type="text" placeholder="Buscar ticket, cliente, SKU..."
               class="md:col-span-2 rounded-xl border-gray-200 text-sm">
        <select wire:model.live="filterStatus" class="rounded-xl border-gray-200 text-sm">
            <option value="">Estados</option>
            @foreach($statusLabels as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterPriority" class="rounded-xl border-gray-200 text-sm">
            <option value="">Prioridades</option>
            @foreach($priorityLabels as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterType" class="rounded-xl border-gray-200 text-sm">
            <option value="">Tipos</option>
            @foreach($typeLabels as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterAssignee" class="rounded-xl border-gray-200 text-sm">
            <option value="">Responsables</option>
            <option value="unassigned">Sin asignar</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
            @endforeach
        </select>
    </div>
</div>
