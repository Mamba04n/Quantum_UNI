<?php

namespace App\Domain\Administracion\Auditoria\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListarActividadQuery
{
    public function ejecutar(
        ?string $usuarioId,
        ?string $modulo,
        ?string $rol,
        ?string $busqueda,
        int $porPagina,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        ?string $origen = null,
        ?string $tipoMovimiento = null
    ): LengthAwarePaginator {
        return app(UnifiedActivityQuery::class)->paginate($usuarioId, $modulo, $rol, $busqueda, $porPagina, $fechaDesde, $fechaHasta, $origen, $tipoMovimiento);
    }
}
