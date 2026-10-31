@php
    $waitingAdmin = $ticket->last_customer_message_at && (
        ! $ticket->last_admin_message_at || $ticket->last_customer_message_at->gt($ticket->last_admin_message_at)
    );
    $priorityClass = [
        'baja' => 'bg-gray-100 text-gray-600',
        'media' => 'bg-blue-50 text-blue-700',
        'alta' => 'bg-amber-50 text-amber-700',
        'urgente' => 'bg-rose-50 text-rose-700',
    ][$ticket->priority] ?? 'bg-gray-100 text-gray-600';
@endphp

<tr class="hover:bg-gray-50/50 {{ $selectedTicketId === $ticket->id ? 'bg-indigo-50/40' : '' }}">
    <td class="px-2 py-3 max-w-[120px] truncate">
        <div class="font-bold text-gray-900 truncate">{{ $ticket->subject }}</div>
        <div class="text-[10px] text-gray-400 font-mono">{{ $ticket->code }}</div>
        @if($waitingAdmin)
            <span class="mt-1 inline-flex px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-700">Sin responder</span>
        @endif
    </td>
    <td class="px-2 py-3 max-w-[150px] truncate">
        <div class="font-semibold text-gray-800 truncate">{{ $ticket->customer?->name ?? 'Cliente' }}</div>
        <div class="text-[10px] text-gray-400 truncate">{{ $ticket->customer?->email }}</div>
    </td>
    <td class="px-2 py-3 max-w-[100px] truncate whitespace-normal leading-tight text-[11px]">{{ $typeLabels[$ticket->type] ?? $ticket->type }}</td>
    <td class="px-2 py-3">
        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $priorityClass }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
    </td>
    <td class="px-2 py-3 text-[11px]">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</td>
    <td class="px-2 py-3 max-w-[100px] truncate text-[11px]">{{ $ticket->assignee?->name ?? 'Sin asignar' }}</td>
    <td class="px-2 py-3 text-right">
        <button wire:click="selectTicket({{ $ticket->id }})" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-2 py-1 rounded-md">Gestionar</button>
    </td>
</tr>
