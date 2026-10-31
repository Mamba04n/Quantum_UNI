@php
    $roleColors = [
        'admin' => 'bg-violet-100 text-violet-800 border-violet-200',
        'agente_finanzas' => 'bg-blue-100 text-blue-800 border-blue-200',
        'operaciones' => 'bg-orange-100 text-orange-800 border-orange-200',
        'ventas' => 'bg-teal-100 text-teal-800 border-teal-200',
        'bodega' => 'bg-stone-100 text-stone-700 border-stone-200',
        'soporte' => 'bg-cyan-100 text-cyan-800 border-cyan-200',
        'compras' => 'bg-amber-100 text-amber-800 border-amber-200',
    ];
@endphp

<tr class="hover:bg-gray-50/50 transition-colors {{ !$user->is_active ? 'opacity-50' : '' }}">
    <td class="px-5 py-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-xs font-black text-slate-600 uppercase shrink-0">
                {{ mb_strtoupper(mb_substr(trim($user->name), 0, 2, 'UTF-8'), 'UTF-8') }}
            </div>
            <div>
                <div class="font-semibold text-gray-900">{{ $user->name }}</div>
                <div class="text-[11px] text-gray-400">
                    {{ ($employeeProfilesReady && $user->relationLoaded('employeeProfile')) ? ($user->employeeProfile?->job_title ?: 'Sin cargo') : 'Perfil pendiente' }}
                </div>
            </div>
        </div>
    </td>
    <td class="px-5 py-3 text-gray-600 font-mono text-xs">{{ $user->email }}</td>
    <td class="px-5 py-3">
        <div class="flex flex-wrap gap-1.5">
            @foreach($user->roleSlugs() as $roleSlug)
                @continue($roleSlug === \App\Models\User::ROLE_CLIENTE_TIENDA)
                <span class="inline-flex text-[11px] font-bold px-2 py-0.5 rounded border {{ $roleColors[$roleSlug] ?? 'bg-gray-100 text-gray-700 border-gray-200' }}">
                    {{ $roles[$roleSlug] ?? $roleSlug }}
                </span>
            @endforeach
        </div>
    </td>
    <td class="px-5 py-3 text-center">
        <button wire:click="toggleActive({{ $user->id }})" @if($user->id === auth()->id()) disabled @endif class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded border transition-colors {{ $user->is_active ? 'bg-teal-50 text-teal-700 border-teal-200 hover:bg-teal-100' : 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100' }} disabled:opacity-40 disabled:cursor-not-allowed">
            {{ $user->is_active ? 'Activo' : 'Inactivo' }}
        </button>
    </td>
    <td class="px-5 py-3 text-center">
        <div class="flex items-center justify-center gap-2">
            <button wire:click="openEdit({{ $user->id }})" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Editar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            @if($user->id !== auth()->id())
                <button wire:click="confirmDelete({{ $user->id }})" class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Eliminar o desactivar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            @endif
        </div>
    </td>
</tr>
