<?php

namespace App\Domain\Clientes\Tickets\Queries;

use App\Models\User;
use Illuminate\Support\Collection;

class ListarAgentesTicketsQuery
{
    public function ejecutar(): Collection
    {
        return User::whereHas('roles', fn($q) => $q->whereIn('slug', ['admin', 'ventas', 'soporte']))
            ->orderBy('name')
            ->get();
    }
}
