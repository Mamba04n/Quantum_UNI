<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
    <div>
        <p class="text-[11px] font-bold uppercase tracking-wider text-[#b50e0e] mb-1">EQUIPO Y ACCESOS</p>
        <h2 class="text-2xl font-bold tracking-tight text-[#1e1e1e]">Equipo Interno</h2>
        <p class="text-xs text-gray-500 font-medium mt-0.5">Usuarios ERP, perfil laboral, roles y acceso operativo.</p>
    </div>
    <button wire:click="openCreate" class="inline-flex items-center justify-center gap-2 bg-[#b50e0e] hover:bg-[#9a0101] text-white text-sm font-semibold px-4 py-2.5 rounded-xl shadow-sm transition-colors">
        <x-portal.icon name="plus" class="w-4 h-4" />
        Nuevo empleado
    </button>
</div>
