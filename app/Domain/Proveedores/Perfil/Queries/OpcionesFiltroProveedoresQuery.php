<?php

namespace App\Domain\Proveedores\Perfil\Queries;

use App\Models\Entity;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class OpcionesFiltroProveedoresQuery
{
    private const COLUMNAS_PERMITIDAS = [
        'country',
        'city',
        'status',
    ];

    public function ejecutar(string $column): Collection
    {
        if (! in_array($column, self::COLUMNAS_PERMITIDAS, true)) {
            throw new InvalidArgumentException("Filtro de proveedores no permitido: {$column}");
        }

        return Entity::where('type', 'proveedor')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }
}
