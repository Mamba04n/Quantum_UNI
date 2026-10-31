<?php

namespace App\Domain\Administracion\Auditoria\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ListarUsuariosAuditoriaQuery
{
    public function ejecutar(): Collection
    {
        return User::orderBy('name')->get();
    }
}
