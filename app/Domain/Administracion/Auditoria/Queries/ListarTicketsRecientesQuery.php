<?php

namespace App\Domain\Administracion\Auditoria\Queries;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

class ListarTicketsRecientesQuery
{
    public function ejecutar(?string $busqueda): Collection
    {
        return Ticket::with(['customer', 'assignee', 'product'])
            ->when($busqueda, fn ($q) => $q->where(function ($search) use ($busqueda) {
                $search->where('code', 'like', "%{$busqueda}%")
                    ->orWhere('subject', 'like', "%{$busqueda}%")
                    ->orWhere('related_sku', 'like', "%{$busqueda}%")
                    ->orWhereHas('customer', fn ($customer) =>
                        $customer->where('name', 'like', "%{$busqueda}%")
                            ->orWhere('email', 'like', "%{$busqueda}%")
                    );
            }))
            ->latest('last_message_at')
            ->latest()
            ->limit(25)
            ->get();
    }
}
