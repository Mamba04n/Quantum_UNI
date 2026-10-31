<div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-3">
        <input aria-label="Buscar actividad" wire:model.live.debounce.500ms="search" type="text" placeholder="Buscar acción, descripción o referencia..." class="rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
        <select wire:model.live="filterUser" class="rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
            <option value="">Todos los usuarios</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }} ({{ $roles[$u->role] ?? $u->role }})</option>
            @endforeach
        </select>
        <select wire:model.live="filterRole" class="rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
            <option value="">Todos los roles</option>
            @foreach($roles as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterModule" class="rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
            @foreach($modules as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <select aria-label="Origen del evento" wire:model.live="filterSource" class="rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
            <option value="">Todos los orígenes</option>
            <option value="actividad">Actividad de negocio</option>
            <option value="auditoria">Cambios, accesos y errores</option>
            <option value="inventario">Movimientos de inventario</option>
            <option value="soporte">Tickets de soporte</option>
            <option value="seguimiento">Seguimiento de pedidos</option>
        </select>
        <select aria-label="Tipo de movimiento de inventario" wire:model.live="filterMoveType" class="rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
            <option value="">Entradas y salidas de inventario</option>
            <option value="in">Entradas</option>
            <option value="out">Salidas</option>
        </select>
        <input aria-label="Fecha desde" wire:model.live="dateFrom" type="date" class="rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
        <input aria-label="Fecha hasta" wire:model.live="dateTo" type="date" class="rounded-lg border-gray-200 text-sm focus:border-slate-700 focus:ring-slate-700">
    </div>
</div>
