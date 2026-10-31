<?php

namespace App\Domain\Clientes\Tickets\Queries;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

class ListarTicketsQuery
{
    public function ejecutar(array $filtros = []): Builder
    {
        return Ticket::query()
            ->with(['customer', 'assignee', 'product'])
            ->when($filtros['search'] ?? null, function (Builder $q, string $search) {
                $q->where(function (Builder $query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('related_sku', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customer) => $customer
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($filtros['filterStatus'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filtros['filterPriority'] ?? null, fn (Builder $q, string $priority) => $q->where('priority', $priority))
            ->when($filtros['filterType'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when(($filtros['filterAssignee'] ?? '') !== '', function (Builder $q) use ($filtros) {
                if (($filtros['filterAssignee'] ?? null) === 'unassigned') {
                    return $q->whereNull('assigned_to');
                }

                return $q->where('assigned_to', $filtros['filterAssignee']);
            })
            ->latest('last_message_at')
            ->latest();
    }
}
