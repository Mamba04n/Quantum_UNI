<?php

namespace App\Domain\Administracion\Usuarios\Queries;

use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;

class ListarUsuariosQuery
{
    public function ejecutar(string $busqueda, bool $perfilesEmpleadosListos): Paginator
    {
        $query = User::query()
            ->with('roles')
            ->when($busqueda, fn ($q) => $q->where(fn ($sq) => $sq->where('name', 'like', "%{$busqueda}%")
                ->orWhere('email', 'like', "%{$busqueda}%")
            )
            );

        if ($perfilesEmpleadosListos) {
            $query->with('employeeProfile');
        }

        $query->whereDoesntHave('roles', fn ($roleQuery) => $roleQuery->where('slug', User::ROLE_CLIENTE_TIENDA));

        return $query->orderBy('name')->simplePaginate(15);
    }
}
