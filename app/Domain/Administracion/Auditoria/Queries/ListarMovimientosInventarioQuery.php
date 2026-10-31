<?php

namespace App\Domain\Administracion\Auditoria\Queries;

use App\Models\StockMove;
use Illuminate\Database\Eloquent\Collection;

class ListarMovimientosInventarioQuery
{
    public function ejecutar(
        ?string $usuarioId,
        ?string $tipoMovimiento,
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?string $busqueda
    ): Collection {
        return StockMove::with(['product', 'user'])
            ->when($usuarioId, fn ($q) => $q->where('user_id', $usuarioId))
            ->when($tipoMovimiento, fn ($q) => $q->where('movement_type', $tipoMovimiento))
            ->when($fechaDesde, fn ($q) => $q->whereDate('movement_date', '>=', $fechaDesde))
            ->when($fechaHasta, fn ($q) => $q->whereDate('movement_date', '<=', $fechaHasta))
            ->when($busqueda, fn ($q) => $q->where(function ($search) use ($busqueda) {
                $search->where('reference', 'like', "%{$busqueda}%")
                    ->orWhereHas('product', fn ($product) =>
                        $product->where('name', 'like', "%{$busqueda}%")
                            ->orWhere('sku', 'like', "%{$busqueda}%")
                    );
            }))
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }
}
