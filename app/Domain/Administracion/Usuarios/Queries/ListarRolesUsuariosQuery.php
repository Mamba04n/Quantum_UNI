<?php

namespace App\Domain\Administracion\Usuarios\Queries;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ListarRolesUsuariosQuery
{
    public function ejecutar(): array
    {
        if (Schema::hasTable('roles')) {
            return Role::where('slug', '!=', User::ROLE_CLIENTE_TIENDA)
                ->orderBy('name')
                ->pluck('name', 'slug')
                ->all();
        }

        return collect(User::$roles)->except(User::ROLE_CLIENTE_TIENDA)->all();
    }
}
